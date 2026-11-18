<?php

namespace App\Services\Payments;

use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Enums\VoucherType;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Receipt;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\User;
use App\Notifications\CourseOrderReceiptNotification;
use App\Services\AccountingService;
use App\Services\Lms\OnlineEnrollmentService;
use App\Services\NumberGeneratorService;
use App\Support\LedgerAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseOrderService
{
    public function __construct(
        protected AccountingService $accounting,
        protected NumberGeneratorService $numbers,
        protected OnlineEnrollmentService $enrollments,
        protected CouponService $coupons,
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * Create (or reuse) a pending order. Price always comes from the course on the server.
     *
     * @return array{order: Order, initiation: GatewayInitiation}
     */
    public function checkout(
        User $user,
        Course $course,
        ?string $gateway = null,
        ?string $couponCode = null,
        ?string $idempotencyKey = null,
    ): array {
        abort_unless($course->is_active && $course->is_published, 404);
        abort_unless($course->hasOnlineComponent(), 404);

        $amount = $this->serverPrice($course);

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'course' => 'This course is free — use the enrol endpoint instead of checkout.',
            ]);
        }

        $gatewayName = $gateway ?: $this->gateways->defaultDriver();
        $gatewayEnum = PaymentGateway::tryFrom($gatewayName);

        if ($gatewayEnum === null) {
            throw ValidationException::withMessages([
                'gateway' => 'Unsupported payment gateway.',
            ]);
        }

        $idempotencyKey = $idempotencyKey !== null && $idempotencyKey !== ''
            ? $idempotencyKey
            : (string) Str::uuid();

        $order = $this->createPendingOrder(
            $user,
            $course,
            $amount,
            $couponCode,
            $gatewayEnum,
            $idempotencyKey,
        );

        // 100% coupon: grant access without hitting a payment gateway or posting a zero journal.
        if (bccomp((string) $order->net_amount, '0', 2) === 0) {
            if ($order->isPending()) {
                $order = $this->fulfillZeroAmountOrder($order);
            }

            return [
                'order' => $order,
                'initiation' => new GatewayInitiation(
                    mode: 'redirect',
                    redirectUrl: route('payments.success', ['order' => $order->order_no]),
                    meta: ['zero_amount' => true],
                ),
            ];
        }

        $driver = $this->gateways->driver($order->gateway->value);
        $initiation = $driver->initiate($order, $this->customerPayload($user, $order));

        PaymentAttempt::query()->create([
            'order_id' => $order->id,
            'gateway' => $order->gateway,
            'request_payload' => [
                'action' => 'initiate',
                'customer' => $this->customerPayload($user, $order),
            ],
            'response_payload' => [
                'mode' => $initiation->mode,
                'redirect_url' => $initiation->redirectUrl,
                'meta' => $initiation->meta,
            ],
            'status' => PaymentAttemptStatus::Pending,
            'created_at' => now(),
        ]);

        if (! empty($initiation->meta['payment_id']) && $order->gateway_txn_id === null) {
            $order->update(['gateway_txn_id' => (string) $initiation->meta['payment_id']]);
        }

        return ['order' => $order->fresh() ?? $order, 'initiation' => $initiation];
    }

    /**
     * Fully discounted order: enrol + mark paid, no cash receipt / journal.
     */
    protected function fulfillZeroAmountOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            /** @var Order $locked */
            $locked = Order::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->with(['course', 'user', 'coupon'])
                ->findOrFail($order->id);

            if ($locked->status === OrderStatus::Paid) {
                return $locked;
            }

            if ($locked->coupon instanceof Coupon) {
                $this->coupons->consume($locked->coupon);
            }

            $enrollment = $this->enrollments->enroll($locked->user, $locked->course);

            $locked->update([
                'status' => OrderStatus::Paid,
                'gateway_txn_id' => 'ZERO-'.$locked->order_no,
                'paid_at' => now(),
                'enrollment_id' => $enrollment->id,
            ]);

            return $locked->fresh(['course', 'user', 'enrollment']) ?? $locked;
        });
    }

    protected function createPendingOrder(
        User $user,
        Course $course,
        string $amount,
        ?string $couponCode,
        PaymentGateway $gateway,
        string $idempotencyKey,
    ): Order {
        return DB::transaction(function () use ($user, $course, $amount, $couponCode, $gateway, $idempotencyKey) {
            $existing = Order::query()
                ->withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Order) {
                return $existing->load(['course', 'student', 'user']);
            }

            $discount = '0.00';
            $couponId = null;
            $net = $amount;

            if (filled($couponCode)) {
                $applied = $this->coupons->validateForCourse($couponCode, $course, $amount);
                $discount = $applied['discount'];
                $net = $applied['net_amount'];
                $couponId = $applied['coupon']->id;
            }

            $student = $this->enrollments->resolveStudent($user);

            return Order::query()->create([
                'order_no' => $this->numbers->nextOrder(),
                'student_id' => $student->id,
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $amount,
                'discount' => $discount,
                'coupon_id' => $couponId,
                'net_amount' => $net,
                'status' => OrderStatus::Pending,
                'gateway' => $gateway,
                'idempotency_key' => $idempotencyKey,
            ])->load(['course', 'student', 'user']);
        });
    }

    /**
     * Confirm a payment after server-side gateway validation. Idempotent.
     *
     * @param  array<string, mixed>  $payload
     */
    public function confirmFromCallback(Order $order, array $payload, bool $fromIpn = false): Order
    {
        $driver = $this->gateways->driver($order->gateway->value);
        $result = $fromIpn
            ? $driver->validateIpn($order, $payload)
            : $driver->validateCallback($order, $payload);

        PaymentAttempt::query()->create([
            'order_id' => $order->id,
            'gateway' => $order->gateway,
            'request_payload' => [
                'action' => $fromIpn ? 'ipn' : 'callback',
                'payload' => $payload,
            ],
            'response_payload' => [
                'success' => $result->success,
                'transaction_id' => $result->transactionId,
                'amount' => $result->amount,
                'message' => $result->message,
                'payload' => $result->payload,
            ],
            'status' => $result->success ? PaymentAttemptStatus::Success : PaymentAttemptStatus::Failed,
            'created_at' => now(),
        ]);

        if (! $result->success) {
            return $this->markFailed($order, $result->message);
        }

        return $this->fulfillPaidOrder($order, $result);
    }

    /**
     * Admin verification for manual gateway payments.
     */
    public function verifyManual(Order $order, string $trxId, ?User $actor = null, ?string $method = null): Order
    {
        if ($order->gateway !== PaymentGateway::Manual) {
            throw ValidationException::withMessages([
                'gateway' => 'Only manual orders can be verified this way.',
            ]);
        }

        if (! $order->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending orders can be verified.',
            ]);
        }

        $order->manual_trx_id = $trxId;
        if ($method !== null) {
            $order->manual_method = $method;
        }
        $order->save();

        return $this->confirmFromCallback($order, [
            'admin_verified' => true,
            'trx_id' => $trxId,
            'amount' => number_format((float) $order->net_amount, 2, '.', ''),
            'verified_by' => $actor?->id,
        ], fromIpn: true);
    }

    public function markFailed(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            /** @var Order $locked */
            $locked = Order::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (in_array($locked->status, [OrderStatus::Paid, OrderStatus::Refunded, OrderStatus::Failed], true)) {
                return $locked;
            }

            $locked->update([
                'status' => OrderStatus::Failed,
            ]);

            PaymentAttempt::query()->create([
                'order_id' => $locked->id,
                'gateway' => $locked->gateway,
                'request_payload' => ['action' => 'mark_failed'],
                'response_payload' => ['reason' => $reason],
                'status' => PaymentAttemptStatus::Failed,
                'created_at' => now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function markCancelled(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            /** @var Order $locked */
            $locked = Order::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (in_array($locked->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                return $locked;
            }

            $locked->update(['status' => OrderStatus::Cancelled]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function refund(Order $order, string $reason, User $actor): Order
    {
        if (! $actor->can('payment.refund')) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to refund payments.',
            ]);
        }

        return DB::transaction(function () use ($order, $reason, $actor) {
            /** @var Order $locked */
            $locked = Order::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->with(['journalEntry', 'enrollment', 'receipt'])
                ->findOrFail($order->id);

            if ($locked->status !== OrderStatus::Paid) {
                throw ValidationException::withMessages([
                    'status' => 'Only paid orders can be refunded.',
                ]);
            }

            if ($locked->journalEntry !== null) {
                $this->accounting->reverse(
                    $locked->journalEntry,
                    $actor,
                    "Refund of order {$locked->order_no}: {$reason}",
                );
            }

            if ($locked->enrollment_id !== null) {
                $enrollment = Enrollment::query()
                    ->withoutGlobalScope(PortalStudentOwnedScope::class)
                    ->lockForUpdate()
                    ->find($locked->enrollment_id);

                if ($enrollment !== null) {
                    $enrollment->update([
                        'status' => EnrollmentStatus::Dropped,
                        'expires_at' => now(),
                    ]);
                }
            }

            $locked->update([
                'status' => OrderStatus::Refunded,
                'refund_reason' => $reason,
                'refunded_at' => now(),
                'refunded_by' => $actor->id,
            ]);

            activity()
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties(['reason' => $reason])
                ->log('order_refunded');

            return $locked->fresh(['journalEntry', 'enrollment', 'receipt']) ?? $locked;
        });
    }

    protected function fulfillPaidOrder(Order $order, GatewayValidationResult $result): Order
    {
        /** @var array{order: Order, newly_paid: bool} $outcome */
        $outcome = DB::transaction(function () use ($order, $result) {
            /** @var Order $locked */
            $locked = Order::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->with(['course', 'student', 'user', 'coupon'])
                ->findOrFail($order->id);

            if (in_array($locked->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                return ['order' => $locked, 'newly_paid' => false];
            }

            if (! in_array($locked->status, [OrderStatus::Pending, OrderStatus::Failed, OrderStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'status' => "Order cannot be paid from status {$locked->status->value}.",
                ]);
            }

            $expected = number_format((float) $locked->net_amount, 2, '.', '');
            $validatedAmount = number_format((float) ($result->amount ?? 0), 2, '.', '');

            if (bccomp($validatedAmount, $expected, 2) !== 0) {
                throw ValidationException::withMessages([
                    'amount' => "Validated amount {$validatedAmount} does not match order net {$expected}.",
                ]);
            }

            if ($locked->coupon instanceof Coupon) {
                $this->coupons->consume($locked->coupon);
            }

            $enrollment = $this->enrollments->enroll($locked->user, $locked->course);

            $method = $this->paymentMethodFor($locked);
            $cashCode = $locked->manual_method === 'nagad'
                ? LedgerAccounts::NAGAD
                : $locked->gateway->defaultLedgerCode();
            $cashAccount = LedgerAccounts::byCode($cashCode);
            $incomeAccount = LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME);

            $receipt = Receipt::query()->create([
                'receipt_no' => $this->numbers->nextFeeReceipt(),
                'student_id' => $locked->student_id,
                'date' => now()->toDateString(),
                'method' => $method,
                'account_id' => $cashAccount->id,
                'amount' => $expected,
                'status' => ReceiptStatus::Posted,
                'idempotency_key' => 'order:'.$locked->order_no,
                'created_by' => $locked->user_id,
                'notes' => "Online course order {$locked->order_no}",
            ]);

            $journal = $this->accounting->createEntry([
                'entry_date' => now()->toDateString(),
                'voucher_type' => VoucherType::Receipt,
                'narration' => "Course order {$locked->order_no} — {$locked->course->name}",
                'reference' => $locked,
                'created_by' => $locked->user_id,
            ], [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => $expected,
                    'student_id' => $locked->student_id,
                    'description' => 'Course payment received',
                ],
                [
                    'account_id' => $incomeAccount->id,
                    'credit' => $expected,
                    'student_id' => $locked->student_id,
                    'description' => 'Tuition / course fee income',
                ],
            ]);

            $journal = $this->accounting->post($journal);
            $receipt->update(['journal_entry_id' => $journal->id]);

            $locked->update([
                'status' => OrderStatus::Paid,
                'gateway_txn_id' => $result->transactionId,
                'paid_at' => now(),
                'enrollment_id' => $enrollment->id,
                'receipt_id' => $receipt->id,
                'journal_entry_id' => $journal->id,
            ]);

            return [
                'order' => $locked->fresh(['course', 'student', 'user', 'enrollment', 'receipt', 'journalEntry']) ?? $locked,
                'newly_paid' => true,
            ];
        });

        if ($outcome['newly_paid']) {
            $this->queueReceiptNotification($outcome['order']);
        }

        return $outcome['order'];
    }

    protected function queueReceiptNotification(Order $order): void
    {
        $order->loadMissing(['user', 'receipt', 'course', 'student']);

        if ($order->user !== null && $order->status === OrderStatus::Paid) {
            $order->user->notify(new CourseOrderReceiptNotification($order));
        }

        if ((bool) setting('notification.sms_enabled', false) && $order->student !== null && filled($order->student->phone)) {
            \App\Jobs\SendNotificationBatch::dispatch(
                [$order->student],
                'sms',
                'Payment received for '.$order->course?->name
                    .' ('.$order->order_no.'). Amount '
                    .setting('general.currency_symbol', '৳')
                    .number_format((float) $order->net_amount, 2),
                ['order_id' => $order->id, 'phone' => $order->student->phone],
            )->onQueue('notifications');
        }
    }

    public function serverPrice(Course $course): string
    {
        $price = $course->effectivePrice();

        return number_format((float) ($price ?? 0), 2, '.', '');
    }

    public function findByOrderNo(string $orderNo): Order
    {
        return Order::query()
            ->withoutGlobalScopes()
            ->where('order_no', $orderNo)
            ->firstOrFail();
    }

    /**
     * @return array{name: string, email: ?string, phone: ?string, address: ?string, product_name: string}
     */
    protected function customerPayload(User $user, Order $order): array
    {
        $order->loadMissing(['student', 'course']);

        return [
            'name' => $order->student?->name ?? $user->name,
            'email' => $order->student?->email ?? $user->email,
            'phone' => $order->student?->phone,
            'address' => $order->student?->address,
            'product_name' => $order->course?->name ?? 'Course',
        ];
    }

    protected function paymentMethodFor(Order $order): PaymentMethod
    {
        if ($order->gateway === PaymentGateway::Manual) {
            return match ($order->manual_method) {
                'nagad' => PaymentMethod::Nagad,
                default => PaymentMethod::Bkash,
            };
        }

        return $order->gateway->defaultPaymentMethod();
    }
}
