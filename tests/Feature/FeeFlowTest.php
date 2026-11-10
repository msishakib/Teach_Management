<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeFrequency;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\StudentInvoiceStatus;
use App\Enums\VoucherType;
use App\Jobs\GenerateBatchInvoicesJob;
use App\Models\Account;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Services\FeeInvoiceService;
use App\Services\FeePaymentService;
use App\Services\ReceiptPdfService;
use App\Support\LedgerAccounts;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FeeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected FeeInvoiceService $invoices;

    protected FeePaymentService $payments;

    protected Batch $batch;

    protected Student $student;

    protected FeeType $tuition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->invoices = app(FeeInvoiceService::class);
        $this->payments = app(FeePaymentService::class);

        $this->tuition = FeeType::factory()->tuition()->create();
        $this->batch = Batch::factory()->create();
        $this->student = Student::factory()->create();

        FeeStructure::factory()->create([
            'course_id' => $this->batch->course_id,
            'fee_type_id' => $this->tuition->id,
            'amount' => 1000,
            'frequency' => FeeFrequency::Monthly,
        ]);

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'batch_id' => $this->batch->id,
            'status' => EnrollmentStatus::Active,
            'enrolled_on' => now()->startOfMonth(),
        ]);
    }

    public function test_generate_invoice_posts_receivable_and_income_journal(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $month = now()->format('Y-m');

        $invoice = $this->invoices->generateForEnrollment($enrollment, $month);

        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->wasRecentlyCreated);
        $this->assertSame('1000.00', (string) $invoice->total);
        $this->assertSame('1000.00', (string) $invoice->due);
        $this->assertSame(StudentInvoiceStatus::Unpaid, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);

        $entry = JournalEntry::query()->with('lines')->findOrFail($invoice->journal_entry_id);
        $this->assertTrue($entry->isPosted());
        $this->assertSame(VoucherType::Journal, $entry->voucher_type);

        $receivableId = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE)->id;
        $incomeId = LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME)->id;

        $this->assertSame('1000.00', number_format((float) $entry->lines->where('account_id', $receivableId)->sum('debit'), 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $entry->lines->where('account_id', $incomeId)->sum('credit'), 2, '.', ''));
    }

    public function test_invoice_generation_is_idempotent(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $month = now()->format('Y-m');

        $first = $this->invoices->generateForEnrollment($enrollment, $month);
        $second = $this->invoices->generateForEnrollment($enrollment, $month);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame(1, StudentInvoice::query()->count());
        $this->assertSame(1, JournalEntry::query()->where('voucher_type', VoucherType::Journal)->count());
    }

    public function test_batch_invoice_job_is_queued_and_chunk_processes_students(): void
    {
        Queue::fake();

        $this->invoices->dispatchGenerateForBatch($this->batch, now()->format('Y-m'));

        Queue::assertPushed(GenerateBatchInvoicesJob::class, function (GenerateBatchInvoicesJob $job) {
            return $job->batchId === $this->batch->id
                && $job->month === now()->format('Y-m');
        });

        $result = $this->invoices->generateChunk($this->batch, now()->format('Y-m'), 0, 50);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, StudentInvoice::query()->count());

        $again = $this->invoices->generateChunk($this->batch, now()->format('Y-m'), 0, 50);
        $this->assertSame(0, $again['created']);
        $this->assertSame(1, $again['skipped']);
        $this->assertSame(1, StudentInvoice::query()->count());
    }

    public function test_partial_payment_updates_invoice_and_posts_receipt_journal(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $invoice = $this->invoices->generateForEnrollment($enrollment, now()->format('Y-m'));

        $receipt = $this->payments->receive([
            'student_id' => $this->student->id,
            'method' => PaymentMethod::Cash,
            'amount' => 400,
            'idempotency_key' => 'pay-partial-1',
        ]);

        $invoice->refresh();

        $this->assertSame('400.00', (string) $receipt->amount);
        $this->assertSame(1, $receipt->allocations()->count());
        $this->assertSame('400.00', (string) $invoice->paid);
        $this->assertSame('600.00', (string) $invoice->due);
        $this->assertSame(StudentInvoiceStatus::Partial, $invoice->status);

        $entry = $receipt->journalEntry()->with('lines')->firstOrFail();
        $cashId = LedgerAccounts::byCode(LedgerAccounts::CASH)->id;
        $receivableId = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE)->id;

        $this->assertSame('400.00', number_format((float) $entry->lines->where('account_id', $cashId)->sum('debit'), 2, '.', ''));
        $this->assertSame('400.00', number_format((float) $entry->lines->where('account_id', $receivableId)->sum('credit'), 2, '.', ''));
    }

    public function test_overpayment_allocates_to_invoices_and_posts_advance(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $invoice = $this->invoices->generateForEnrollment($enrollment, now()->format('Y-m'));

        $receipt = $this->payments->receive([
            'student_id' => $this->student->id,
            'method' => PaymentMethod::Bank,
            'amount' => 1500,
            'idempotency_key' => 'pay-over-1',
        ]);

        $invoice->refresh();
        $entry = $receipt->journalEntry()->with('lines')->firstOrFail();

        $this->assertSame(StudentInvoiceStatus::Paid, $invoice->status);
        $this->assertSame('1000.00', (string) $invoice->paid);
        $this->assertSame('0.00', (string) $invoice->due);
        $this->assertSame('1000.00', number_format((float) $receipt->allocations()->sum('amount'), 2, '.', ''));

        $advanceId = LedgerAccounts::byCode(LedgerAccounts::ADVANCE_FROM_STUDENTS)->id;
        $receivableId = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE)->id;
        $bankId = LedgerAccounts::byCode(LedgerAccounts::BANK)->id;

        $this->assertSame('1500.00', number_format((float) $entry->lines->where('account_id', $bankId)->sum('debit'), 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $entry->lines->where('account_id', $receivableId)->sum('credit'), 2, '.', ''));
        $this->assertSame('500.00', number_format((float) $entry->lines->where('account_id', $advanceId)->sum('credit'), 2, '.', ''));
    }

    public function test_double_submit_with_same_idempotency_key_returns_same_receipt(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $this->invoices->generateForEnrollment($enrollment, now()->format('Y-m'));

        $first = $this->payments->receive([
            'student_id' => $this->student->id,
            'method' => PaymentMethod::Cash,
            'amount' => 300,
            'idempotency_key' => 'duplicate-submit-key',
        ]);

        $second = $this->payments->receive([
            'student_id' => $this->student->id,
            'method' => PaymentMethod::Cash,
            'amount' => 300,
            'idempotency_key' => 'duplicate-submit-key',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame(1, JournalEntry::query()->where('voucher_type', VoucherType::Receipt)->count());

        $invoice = StudentInvoice::query()->firstOrFail();
        $this->assertSame('300.00', (string) $invoice->paid);
    }

    public function test_manual_allocation_honors_selected_invoices(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $current = $this->invoices->generateForEnrollment($enrollment, now()->format('Y-m'));
        $prior = $this->invoices->generateForEnrollment($enrollment, now()->subMonth()->format('Y-m'));

        $receipt = $this->payments->receive([
            'student_id' => $this->student->id,
            'method' => PaymentMethod::Bkash,
            'amount' => 250,
            'allocations' => [
                ['student_invoice_id' => $current->id, 'amount' => 250],
            ],
            'idempotency_key' => 'manual-alloc-1',
        ]);

        $current->refresh();
        $prior->refresh();

        $this->assertSame($current->id, $receipt->allocations()->first()->student_invoice_id);
        $this->assertSame('250.00', (string) $current->paid);
        $this->assertSame('0.00', (string) $prior->paid);
        $this->assertSame(StudentInvoiceStatus::Unpaid, $prior->status);
    }

    public function test_discount_requires_fee_approve_and_posts_discount_journal(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $invoice = $this->invoices->generateForEnrollment($enrollment, now()->format('Y-m'));

        $unauthorized = User::factory()->accountant()->create();
        // accountant role has fee.* including approve in matrix — strip approve for this assertion
        $unauthorized->givePermissionTo('fee.view');

        try {
            $this->invoices->applyDiscount($unauthorized, $invoice, 100);
            $this->fail('Expected ValidationException for missing fee.approve');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permission', $exception->errors());
        }

        $approver = User::factory()->admin()->create();
        $approver->assignRole(RoleName::Admin->value);

        $updated = $this->invoices->applyDiscount($approver, $invoice, 100, 'Hardship waiver');

        $this->assertSame('100.00', (string) $updated->discount);
        $this->assertSame('900.00', (string) $updated->due);
        $this->assertSame(StudentInvoiceStatus::Unpaid, $updated->status);

        $discountId = LedgerAccounts::byCode(LedgerAccounts::DISCOUNT_ALLOWED)->id;
        $receivableId = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE)->id;

        $discountEntry = JournalEntry::query()
            ->where('narration', 'like', '%Hardship waiver%')
            ->with('lines')
            ->firstOrFail();

        $this->assertSame('100.00', number_format((float) $discountEntry->lines->where('account_id', $discountId)->sum('debit'), 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $discountEntry->lines->where('account_id', $receivableId)->sum('credit'), 2, '.', ''));
    }

    public function test_receipt_pdf_renders_with_institute_settings(): void
    {
        $enrollment = Enrollment::query()->where('student_id', $this->student->id)->firstOrFail();
        $this->invoices->generateForEnrollment($enrollment, now()->format('Y-m'));

        $receipt = $this->payments->receive([
            'student_id' => $this->student->id,
            'method' => PaymentMethod::Cash,
            'amount' => 100,
            'idempotency_key' => 'pdf-1',
        ]);

        $response = app(ReceiptPdfService::class)->stream($receipt);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }
}
