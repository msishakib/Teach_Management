<?php

namespace Tests\Feature;

use App\Enums\DeliveryMode;
use App\Enums\EnrollmentStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\CourseAccessService;
use App\Services\Payments\CourseOrderService;
use App\Support\LedgerAccounts;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CourseOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected CourseOrderService $orders;

    protected Course $course;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        config(['payments.default' => 'fake']);

        $this->orders = app(CourseOrderService::class);

        $this->course = Course::factory()->create([
            'is_active' => true,
            'is_published' => true,
            'delivery_mode' => DeliveryMode::Online,
            'price' => 1000,
            'sale_price' => null,
            'access_days' => 30,
        ]);

        $this->user = User::factory()->create(['type' => UserType::Student]);
        $this->user->assignRole(RoleName::Student->value);
        Student::factory()->create([
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);
    }

    public function test_double_click_on_pay_creates_one_order(): void
    {
        $key = 'idem-double-click-1';

        $first = $this->orders->checkout($this->user, $this->course, 'fake', null, $key);
        $second = $this->orders->checkout($this->user, $this->course, 'fake', null, $key);

        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertSame(1, Order::query()->withoutGlobalScopes()->count());
    }

    public function test_webhook_idempotency_does_not_double_enroll_or_journal(): void
    {
        Notification::fake();

        $checkout = $this->orders->checkout($this->user, $this->course, 'fake', null, 'idem-webhook');
        $order = $checkout['order'];

        $payload = [
            'status' => 'success',
            'amount' => '1000.00',
            'tran_id' => 'FAKE-'.$order->order_no,
        ];

        $paid = $this->orders->confirmFromCallback($order, $payload, fromIpn: true);
        $again = $this->orders->confirmFromCallback($order->fresh(), $payload, fromIpn: true);

        $this->assertSame(OrderStatus::Paid, $paid->status);
        $this->assertSame($paid->id, $again->id);
        $this->assertSame($paid->enrollment_id, $again->enrollment_id);
        $this->assertSame($paid->journal_entry_id, $again->journal_entry_id);

        $this->assertSame(1, Enrollment::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, Receipt::query()->withoutGlobalScopes()->where('idempotency_key', 'order:'.$order->order_no)->count());
        $this->assertSame(1, JournalEntry::query()->where('reference_type', (new Order)->getMorphClass())->where('reference_id', $order->id)->count());
    }

    public function test_tampered_amount_is_rejected(): void
    {
        Notification::fake();

        $checkout = $this->orders->checkout($this->user, $this->course, 'fake', null, 'idem-tamper');
        $order = $checkout['order'];

        $result = $this->orders->confirmFromCallback($order, [
            'status' => 'success',
            'amount' => '1.00',
            'tran_id' => 'FAKE-TAMPER',
        ], fromIpn: true);

        $this->assertSame(OrderStatus::Failed, $result->status);
        $this->assertSame(0, Enrollment::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, Receipt::query()->withoutGlobalScopes()->count());
        $this->assertFalse(
            app(CourseAccessService::class)->canAccessCourse($this->user->fresh(), $this->course)
        );
    }

    public function test_failed_payment_gives_no_access(): void
    {
        $checkout = $this->orders->checkout($this->user, $this->course, 'fake', null, 'idem-fail');
        $order = $this->orders->markFailed($checkout['order'], 'declined');

        $this->assertSame(OrderStatus::Failed, $order->status);
        $this->assertSame(0, Enrollment::query()->withoutGlobalScopes()->count());
        $this->assertFalse(
            app(CourseAccessService::class)->canAccessCourse($this->user->fresh(), $this->course)
        );
    }

    public function test_coupon_limits_and_course_scope(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE50',
            'type' => 'percent',
            'value' => 50,
            'max_uses' => 1,
            'used_count' => 0,
            'course_id' => $this->course->id,
        ]);

        Notification::fake();

        $checkout = $this->orders->checkout($this->user, $this->course, 'fake', 'SAVE50', 'idem-coupon');
        $this->assertSame('500.00', (string) $checkout['order']->net_amount);
        $this->assertSame('500.00', (string) $checkout['order']->discount);

        $this->orders->confirmFromCallback($checkout['order'], [
            'status' => 'success',
            'amount' => '500.00',
            'tran_id' => 'FAKE-COUPON',
        ], fromIpn: true);

        $this->assertSame(1, $coupon->fresh()->used_count);

        $otherUser = User::factory()->create(['type' => UserType::Student]);
        $otherUser->assignRole(RoleName::Student->value);
        Student::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(ValidationException::class);
        $this->orders->checkout($otherUser, $this->course, 'fake', 'SAVE50', 'idem-coupon-2');
    }

    public function test_coupon_rejects_wrong_course(): void
    {
        Coupon::factory()->create([
            'code' => 'OTHER',
            'course_id' => Course::factory()->create()->id,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->orders->checkout($this->user, $this->course, 'fake', 'OTHER', 'idem-scope');
    }

    public function test_refund_reverses_accounting_and_cancels_enrollment(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $checkout = $this->orders->checkout($this->user, $this->course, 'fake', null, 'idem-refund');
        $order = $this->orders->confirmFromCallback($checkout['order'], [
            'status' => 'success',
            'amount' => '1000.00',
            'tran_id' => 'FAKE-REFUND',
        ], fromIpn: true);

        $journalId = $order->journal_entry_id;
        $enrollmentId = $order->enrollment_id;

        $refunded = $this->orders->refund($order, 'Student requested refund', $admin);

        $this->assertSame(OrderStatus::Refunded, $refunded->status);
        $this->assertSame('Student requested refund', $refunded->refund_reason);

        $original = JournalEntry::query()->findOrFail($journalId);
        $this->assertTrue($original->isReversed());

        $enrollment = Enrollment::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->findOrFail($enrollmentId);
        $this->assertSame(EnrollmentStatus::Dropped, $enrollment->status);

        $this->assertFalse(
            app(CourseAccessService::class)->canAccessCourse($this->user->fresh(), $this->course)
        );
    }

    public function test_successful_payment_posts_journal_and_grants_access(): void
    {
        Notification::fake();

        $checkout = $this->orders->checkout($this->user, $this->course, 'fake', null, 'idem-ok');
        $order = $this->orders->confirmFromCallback($checkout['order'], [
            'status' => 'success',
            'amount' => '1000.00',
            'tran_id' => 'FAKE-OK',
        ], fromIpn: false);

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->enrollment_id);
        $this->assertNotNull($order->receipt_id);
        $this->assertNotNull($order->journal_entry_id);

        $entry = JournalEntry::query()->with('lines')->findOrFail($order->journal_entry_id);
        $this->assertSame(JournalEntryStatus::Posted, $entry->status);

        $bankId = LedgerAccounts::byCode(LedgerAccounts::BANK)->id;
        $incomeId = LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME)->id;

        $this->assertTrue($entry->lines->contains(fn ($l) => (int) $l->account_id === $bankId && (float) $l->debit === 1000.0));
        $this->assertTrue($entry->lines->contains(fn ($l) => (int) $l->account_id === $incomeId && (float) $l->credit === 1000.0));

        $this->assertTrue(
            app(CourseAccessService::class)->canAccessCourse($this->user->fresh(), $this->course)
        );
    }

    public function test_paid_course_enroll_redirects_to_checkout(): void
    {
        $this->actingAs($this->user)
            ->post("/en/courses/{$this->course->code}/enroll")
            ->assertRedirect(route('courses.checkout', [
                'locale' => 'en',
                'course' => $this->course->code,
            ]));

        $this->assertSame(0, Enrollment::query()->withoutGlobalScopes()->count());
    }

    public function test_http_checkout_and_fake_payment_flow(): void
    {
        Notification::fake();

        $this->actingAs($this->user)
            ->post("/en/courses/{$this->course->code}/checkout", [
                'gateway' => 'fake',
                'idempotency_key' => 'http-flow-1',
            ])
            ->assertRedirect();

        $order = Order::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentGateway::Fake, $order->gateway);

        $this->actingAs($this->user)
            ->post(route('payments.fake.pay.submit', ['order' => $order->order_no]), [
                'outcome' => 'success',
            ])
            ->assertRedirect(route('payments.success', ['order' => $order->order_no]));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_payment_result_pages_render_localized_course_links(): void
    {
        Notification::fake();

        $checkout = $this->orders->checkout($this->user, $this->course, 'fake', null, 'idem-result-pages');
        $order = $this->orders->confirmFromCallback($checkout['order'], [
            'status' => 'success',
            'amount' => '1000.00',
            'tran_id' => 'FAKE-RESULT',
        ], fromIpn: false);

        $this->actingAs($this->user)
            ->get(route('payments.success', ['order' => $order->order_no]))
            ->assertOk()
            ->assertSee('/courses/'.$this->course->code.'/learn', false);

        $this->actingAs($this->user)
            ->get(route('payments.failed', ['order' => $order->order_no]))
            ->assertOk()
            ->assertSee('/courses/'.$this->course->code.'/checkout', false);

        $this->actingAs($this->user)
            ->get(route('payments.cancelled', ['order' => $order->order_no]))
            ->assertOk()
            ->assertSee('/courses/'.$this->course->code, false);
    }
}
