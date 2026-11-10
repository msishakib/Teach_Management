<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentVoucherStatus;
use App\Enums\RoleName;
use App\Enums\TeacherSalaryStatus;
use App\Enums\VoucherType;
use App\Jobs\GenerateTeacherSalariesJob;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\PaymentVoucherPdfService;
use App\Services\PaymentVoucherService;
use App\Services\SettingsService;
use App\Services\TeacherSalaryService;
use App\Support\LedgerAccounts;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentVoucherService $vouchers;

    protected TeacherSalaryService $salaries;

    protected Account $cash;

    protected Account $rent;

    protected Account $salaryExpense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->vouchers = app(PaymentVoucherService::class);
        $this->salaries = app(TeacherSalaryService::class);

        $this->cash = Account::query()->where('code', LedgerAccounts::CASH)->firstOrFail();
        $this->rent = Account::query()->where('code', LedgerAccounts::RENT)->firstOrFail();
        $this->salaryExpense = Account::query()->where('code', LedgerAccounts::SALARY_EXPENSE)->firstOrFail();

        $this->cash->update(['opening_balance' => 10000]);
    }

    public function test_approve_posts_expense_and_cash_journal(): void
    {
        $approver = $this->approver();

        $voucher = $this->vouchers->create([
            'account_id' => $this->rent->id,
            'paid_from_account_id' => $this->cash->id,
            'amount' => 1500,
            'method' => PaymentMethod::Cash,
            'narration' => 'Office rent',
            'created_by' => $approver,
        ]);

        $this->assertTrue($voucher->isDraft());

        $posted = $this->vouchers->approve($approver, $voucher);

        $this->assertSame(PaymentVoucherStatus::Posted, $posted->status);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertNotNull($posted->approved_at);

        $entry = JournalEntry::query()->with('lines')->findOrFail($posted->journal_entry_id);
        $this->assertTrue($entry->isPosted());
        $this->assertSame(VoucherType::Payment, $entry->voucher_type);
        $this->assertSame(
            '1500.00',
            number_format((float) $entry->lines->where('account_id', $this->rent->id)->sum('debit'), 2, '.', '')
        );
        $this->assertSame(
            '1500.00',
            number_format((float) $entry->lines->where('account_id', $this->cash->id)->sum('credit'), 2, '.', '')
        );

        $this->assertSame(
            '8500.00',
            app(AccountBalanceService::class)->balance($this->cash->fresh())
        );
    }

    public function test_approve_blocked_when_insufficient_cash_and_negative_not_allowed(): void
    {
        app(SettingsService::class)->set('finance.allow_negative_cash', false);

        $this->cash->update(['opening_balance' => 100]);
        $approver = $this->approver();

        $voucher = $this->vouchers->create([
            'account_id' => $this->rent->id,
            'paid_from_account_id' => $this->cash->id,
            'amount' => 500,
            'method' => PaymentMethod::Cash,
            'created_by' => $approver,
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->vouchers->approve($approver, $voucher);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
            $this->assertSame(PaymentVoucherStatus::Draft, $voucher->fresh()->status);
            $this->assertSame(0, JournalEntry::query()->count());
            throw $exception;
        }
    }

    public function test_approve_allowed_when_negative_cash_setting_enabled(): void
    {
        app(SettingsService::class)->set('finance.allow_negative_cash', true);

        $this->cash->update(['opening_balance' => 50]);
        $approver = $this->approver();

        $voucher = $this->vouchers->create([
            'account_id' => $this->rent->id,
            'paid_from_account_id' => $this->cash->id,
            'amount' => 200,
            'method' => PaymentMethod::Cash,
            'created_by' => $approver,
        ]);

        $posted = $this->vouchers->approve($approver, $voucher);

        $this->assertTrue($posted->isPosted());
        $this->assertSame(
            '-150.00',
            app(AccountBalanceService::class)->balance($this->cash->fresh())
        );
    }

    public function test_approve_requires_payment_approve_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('payment.create');

        $voucher = $this->vouchers->create([
            'account_id' => $this->rent->id,
            'paid_from_account_id' => $this->cash->id,
            'amount' => 100,
            'method' => PaymentMethod::Cash,
            'created_by' => $user,
        ]);

        $this->expectException(ValidationException::class);
        $this->vouchers->approve($user, $voucher);
    }

    public function test_salary_generation_is_queued_chunked_and_idempotent(): void
    {
        Teacher::factory()->count(2)->create(['salary' => 20000]);
        Teacher::factory()->create(['salary' => null]);

        Queue::fake();
        $this->salaries->dispatchGenerateForMonth(now()->format('Y-m'));
        Queue::assertPushed(GenerateTeacherSalariesJob::class);

        $month = now()->format('Y-m');
        $first = $this->salaries->generateChunk($month);
        $this->assertSame(2, $first['created']);

        $second = $this->salaries->generateChunk($month);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(2, TeacherSalary::query()->where('month', $month)->count());
    }

    public function test_pay_salary_creates_voucher_and_approve_marks_paid(): void
    {
        $this->cash->update(['opening_balance' => 50000]);

        $approver = $this->approver();
        $teacher = Teacher::factory()->create(['salary' => 25000, 'name' => 'Ada Teacher']);
        $salary = $this->salaries->generateForTeacher($teacher, now()->format('Y-m'));

        $voucher = $this->salaries->pay($approver, $salary, [
            'paid_from_account_id' => $this->cash->id,
            'method' => PaymentMethod::Cash,
        ]);

        $this->assertSame(PaymentVoucherStatus::Draft, $voucher->status);
        $this->assertSame($this->salaryExpense->id, $voucher->account_id);
        $this->assertSame('25000.00', (string) $voucher->amount);
        $this->assertSame(Teacher::class, $voucher->payee_type);
        $this->assertSame($teacher->id, $voucher->payee_id);
        $this->assertSame(TeacherSalaryStatus::VoucherCreated, $salary->fresh()->status);

        $posted = $this->vouchers->approve($approver, $voucher);

        $this->assertTrue($posted->isPosted());
        $this->assertSame(TeacherSalaryStatus::Paid, $salary->fresh()->status);
    }

    public function test_payment_voucher_pdf_renders(): void
    {
        $approver = $this->approver();
        $voucher = $this->vouchers->create([
            'account_id' => $this->rent->id,
            'paid_from_account_id' => $this->cash->id,
            'amount' => 300,
            'method' => PaymentMethod::Cash,
            'narration' => 'Utility share',
            'created_by' => $approver,
        ]);

        $response = app(PaymentVoucherPdfService::class)->stream($voucher);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    protected function approver(): User
    {
        $user = User::factory()->admin()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }
}
