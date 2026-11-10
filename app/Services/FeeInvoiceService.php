<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeFrequency;
use App\Enums\StudentInvoiceStatus;
use App\Enums\VoucherType;
use App\Jobs\GenerateBatchInvoicesJob;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Support\LedgerAccounts;
use App\Support\PermissionName;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeInvoiceService
{
    public function __construct(
        protected AccountingService $accounting,
        protected NumberGeneratorService $numbers,
        protected SettingsService $settings,
    ) {}

    public function dispatchGenerateForBatch(Batch $batch, string $month, ?User $actor = null): void
    {
        $month = $this->normalizeMonth($month);

        GenerateBatchInvoicesJob::dispatch($batch->id, $month, $actor?->id);
    }

    /**
     * Process one enrollment chunk for a batch/month. Idempotent per student+batch+month.
     *
     * @return array{created: int, skipped: int, last_enrollment_id: ?int, processed: int}
     */
    public function generateChunk(Batch $batch, string $month, int $afterEnrollmentId = 0, int $limit = 100, ?User $actor = null): array
    {
        $month = $this->normalizeMonth($month);

        $enrollments = Enrollment::query()
            ->with(['student'])
            ->where('batch_id', $batch->id)
            ->where('status', EnrollmentStatus::Active)
            ->where('id', '>', $afterEnrollmentId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($enrollments as $enrollment) {
            $invoice = $this->generateForEnrollment($enrollment, $month, $actor);

            if ($invoice === null) {
                $skipped++;
            } elseif ($invoice->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'last_enrollment_id' => $enrollments->last()?->id,
            'processed' => $enrollments->count(),
        ];
    }

    public function generateForEnrollment(Enrollment $enrollment, string $month, ?User $actor = null): ?StudentInvoice
    {
        $month = $this->normalizeMonth($month);

        return DB::transaction(function () use ($enrollment, $month, $actor) {
            $existing = StudentInvoice::query()
                ->where('student_id', $enrollment->student_id)
                ->where('batch_id', $enrollment->batch_id)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->wasRecentlyCreated = false;

                return $existing;
            }

            $batch = Batch::query()->with('course')->findOrFail($enrollment->batch_id);
            $structures = $this->structuresForMonth($batch->course_id, $month, $enrollment);

            if ($structures->isEmpty()) {
                return null;
            }

            $total = $structures->reduce(
                fn (string $carry, FeeStructure $structure) => bcadd($carry, number_format((float) $structure->amount, 2, '.', ''), 2),
                '0.00'
            );

            $dueDays = max(0, (int) $this->settings->get('finance.invoice_due_days', 10));
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            $invoice = StudentInvoice::query()->create([
                'student_id' => $enrollment->student_id,
                'batch_id' => $enrollment->batch_id,
                'invoice_no' => $this->numbers->nextInvoice(),
                'month' => $month,
                'due_date' => $monthStart->copy()->addDays($dueDays)->toDateString(),
                'total' => $total,
                'discount' => '0.00',
                'paid' => '0.00',
                'due' => $total,
                'status' => StudentInvoiceStatus::Unpaid,
            ]);

            foreach ($structures as $structure) {
                $invoice->items()->create([
                    'fee_type_id' => $structure->fee_type_id,
                    'fee_structure_id' => $structure->id,
                    'description' => $structure->feeType?->name ?? 'Fee',
                    'amount' => $structure->amount,
                ]);
            }

            $journal = $this->postInvoiceRecognition($invoice, $actor);
            $invoice->update(['journal_entry_id' => $journal->id]);

            $invoice->wasRecentlyCreated = true;

            return $invoice->load(['items.feeType', 'journalEntry']);
        });
    }

    /**
     * Apply an approved discount/waiver. Requires fee.approve.
     * Posts: Dr Discount Allowed / Cr Student Receivable.
     */
    public function applyDiscount(User $actor, StudentInvoice $invoice, float|int|string $amount, ?string $reason = null): StudentInvoice
    {
        if (! $actor->can(PermissionName::make('fee', 'approve'))) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to approve fee discounts or waivers.',
            ]);
        }

        $discountAmount = number_format((float) $amount, 2, '.', '');

        if (bccomp($discountAmount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Discount amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($actor, $invoice, $discountAmount, $reason) {
            /** @var StudentInvoice $invoice */
            $invoice = StudentInvoice::query()->lockForUpdate()->with('items.feeType')->findOrFail($invoice->id);

            if (! $invoice->isOpen()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Only unpaid or partially paid invoices can receive a discount.',
                ]);
            }

            if (bccomp($discountAmount, (string) $invoice->due, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Discount {$discountAmount} exceeds invoice due {$invoice->due}.",
                ]);
            }

            $receivable = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE);
            $discountAccount = LedgerAccounts::byCode(LedgerAccounts::DISCOUNT_ALLOWED);

            $entry = $this->accounting->createEntry([
                'entry_date' => now()->toDateString(),
                'voucher_type' => VoucherType::Journal,
                'narration' => $reason ?: "Discount/waiver on {$invoice->invoice_no}",
                'reference' => $invoice,
                'created_by' => $actor,
            ], [
                [
                    'account_id' => $discountAccount->id,
                    'debit' => $discountAmount,
                    'credit' => 0,
                    'student_id' => $invoice->student_id,
                    'description' => 'Discount allowed',
                ],
                [
                    'account_id' => $receivable->id,
                    'debit' => 0,
                    'credit' => $discountAmount,
                    'student_id' => $invoice->student_id,
                    'description' => 'Reduce student receivable',
                ],
            ]);

            $this->accounting->post($entry);

            $invoice->discount = bcadd((string) $invoice->discount, $discountAmount, 2);
            $invoice->due = bcsub(
                bcsub((string) $invoice->total, (string) $invoice->discount, 2),
                (string) $invoice->paid,
                2
            );
            $invoice->status = $invoice->recalculateStatus();
            $invoice->save();

            return $invoice->fresh(['items', 'journalEntry']) ?? $invoice;
        });
    }

    /**
     * @return Collection<int, FeeStructure>
     */
    protected function structuresForMonth(int $courseId, string $month, Enrollment $enrollment): Collection
    {
        $structures = FeeStructure::query()
            ->with('feeType.incomeAccount')
            ->where('course_id', $courseId)
            ->whereHas('feeType', fn ($q) => $q->where('is_active', true))
            ->where(function ($query) {
                $query->where('frequency', FeeFrequency::Monthly)
                    ->orWhere('frequency', FeeFrequency::OneTime);
            })
            ->get();

        return $structures->filter(function (FeeStructure $structure) use ($enrollment, $month) {
            if ($structure->frequency === FeeFrequency::Monthly) {
                return true;
            }

            if ($structure->frequency === FeeFrequency::OneTime) {
                $enrolledMonth = Carbon::parse($enrollment->enrolled_on)->format('Y-m');

                return $enrolledMonth === $month;
            }

            return false;
        })->values();
    }

    protected function postInvoiceRecognition(StudentInvoice $invoice, ?User $actor): \App\Models\JournalEntry
    {
        $receivable = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE);
        $defaultIncome = LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME);

        $creditLines = [];
        $incomeTotals = [];

        foreach ($invoice->items()->with('feeType')->get() as $item) {
            $accountId = $item->feeType?->income_account_id ?: $defaultIncome->id;
            $incomeTotals[$accountId] = bcadd($incomeTotals[$accountId] ?? '0.00', (string) $item->amount, 2);
        }

        foreach ($incomeTotals as $accountId => $amount) {
            $creditLines[] = [
                'account_id' => (int) $accountId,
                'debit' => 0,
                'credit' => $amount,
                'student_id' => $invoice->student_id,
                'description' => "Fee income {$invoice->invoice_no}",
            ];
        }

        $lines = array_merge([
            [
                'account_id' => $receivable->id,
                'debit' => $invoice->total,
                'credit' => 0,
                'student_id' => $invoice->student_id,
                'description' => "Invoice {$invoice->invoice_no}",
            ],
        ], $creditLines);

        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
            'narration' => "Fee invoice {$invoice->invoice_no} for {$invoice->month}",
            'reference' => $invoice,
            'created_by' => $actor,
        ], $lines);

        return $this->accounting->post($entry);
    }

    /**
     * Generate Exam Fee invoices for all active enrollments in the exam batch.
     * Idempotent per student+exam via unique (student_id, exam_id).
     *
     * @return array{created: int, skipped: int}
     */
    public function generateExamFees(Exam $exam, ?User $actor = null): array
    {
        if (! $exam->hasFee()) {
            throw ValidationException::withMessages([
                'fee_amount' => 'Exam has no fee amount.',
            ]);
        }

        $examFeeType = FeeType::query()
            ->where('code', 'EXAM')
            ->where('is_active', true)
            ->first();

        if (! $examFeeType) {
            throw ValidationException::withMessages([
                'fee_type' => 'Active fee type "Exam Fee" (EXAM) was not found.',
            ]);
        }

        $created = 0;
        $skipped = 0;

        Enrollment::query()
            ->where('batch_id', $exam->batch_id)
            ->where('status', EnrollmentStatus::Active)
            ->orderBy('id')
            ->chunkById(100, function (Collection $enrollments) use ($exam, $examFeeType, $actor, &$created, &$skipped) {
                foreach ($enrollments as $enrollment) {
                    $invoice = $this->generateExamFeeForEnrollment($enrollment, $exam, $examFeeType, $actor);

                    if ($invoice === null) {
                        $skipped++;
                    } elseif ($invoice->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return compact('created', 'skipped');
    }

    public function generateExamFeeForEnrollment(
        Enrollment $enrollment,
        Exam $exam,
        ?FeeType $examFeeType = null,
        ?User $actor = null,
    ): ?StudentInvoice {
        $examFeeType ??= FeeType::query()
            ->where('code', 'EXAM')
            ->where('is_active', true)
            ->first();

        if (! $examFeeType || ! $exam->hasFee()) {
            return null;
        }

        $amount = number_format((float) $exam->fee_amount, 2, '.', '');
        $monthKey = 'exam:'.$exam->id;

        return DB::transaction(function () use ($enrollment, $exam, $examFeeType, $amount, $monthKey, $actor) {
            $existing = StudentInvoice::query()
                ->where('student_id', $enrollment->student_id)
                ->where('exam_id', $exam->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->wasRecentlyCreated = false;

                return $existing;
            }

            $dueDays = max(0, (int) $this->settings->get('finance.invoice_due_days', 10));

            $invoice = StudentInvoice::query()->create([
                'student_id' => $enrollment->student_id,
                'batch_id' => $enrollment->batch_id,
                'exam_id' => $exam->id,
                'invoice_no' => $this->numbers->nextInvoice(),
                'month' => $monthKey,
                'due_date' => now()->addDays($dueDays)->toDateString(),
                'total' => $amount,
                'discount' => '0.00',
                'paid' => '0.00',
                'due' => $amount,
                'status' => StudentInvoiceStatus::Unpaid,
            ]);

            $exam->loadMissing('batch');

            $structure = FeeStructure::query()
                ->where('course_id', $exam->batch?->course_id)
                ->where('fee_type_id', $examFeeType->id)
                ->first();

            $invoice->items()->create([
                'fee_type_id' => $examFeeType->id,
                'fee_structure_id' => $structure?->id,
                'description' => ($examFeeType->name ?: 'Exam Fee').' — '.$exam->name,
                'amount' => $amount,
            ]);

            $journal = $this->postInvoiceRecognition($invoice->load('items.feeType'), $actor);
            $invoice->update(['journal_entry_id' => $journal->id]);

            $invoice->wasRecentlyCreated = true;

            return $invoice->load(['items.feeType', 'journalEntry']);
        });
    }

    protected function normalizeMonth(string $month): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $month)->format('Y-m');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'month' => 'Month must be in Y-m format.',
            ]);
        }
    }
}
