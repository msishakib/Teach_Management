<?php

namespace Tests\Feature;

use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use App\Support\LedgerAccounts;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\SettingsSeeder;
use App\Models\AccountBalanceDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accounting;

    protected FinancialReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        Cache::flush();

        $this->accounting = app(AccountingService::class);
        $this->reports = app(FinancialReportService::class);

        Account::query()->where('code', LedgerAccounts::CASH)->update(['opening_balance' => 10000]);
        Account::query()->where('code', '3100')->update(['opening_balance' => 10000]);
    }

    public function test_posted_journal_debits_equal_credits_and_balance_sheet_equation_holds(): void
    {
        $user = User::factory()->create();
        $cash = LedgerAccounts::byCode(LedgerAccounts::CASH);
        $receivable = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE);
        $income = LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME);
        $rent = LedgerAccounts::byCode(LedgerAccounts::RENT);

        $invoice = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
            'narration' => 'Fee invoice',
            'created_by' => $user,
        ], [
            ['account_id' => $receivable->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ]);
        $this->accounting->post($invoice);

        $receipt = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Receipt,
            'narration' => 'Fee receipt',
            'created_by' => $user,
        ], [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $receivable->id, 'debit' => 0, 'credit' => 1000],
        ]);
        $this->accounting->post($receipt);

        $payment = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Payment,
            'narration' => 'Rent',
            'created_by' => $user,
        ], [
            ['account_id' => $rent->id, 'debit' => 200, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 200],
        ]);
        $this->accounting->post($payment);

        $journalTotals = $this->reports->postedJournalTotals();
        $this->assertTrue($journalTotals['balanced']);
        $this->assertSame($journalTotals['total_debit'], $journalTotals['total_credit']);
        $this->assertSame('2200.00', $journalTotals['total_debit']);

        $trial = $this->reports->trialBalance(
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );
        $this->assertSame($trial['total_debit'], $trial['total_credit']);

        $sheet = $this->reports->balanceSheet(now()->toDateString());
        $this->assertTrue($sheet['balanced']);
        $this->assertSame(
            $sheet['total_assets'],
            bcadd($sheet['total_liabilities'], $sheet['total_equity'], 2),
        );
        $this->assertSame('800.00', $sheet['current_period_profit']);
        $this->assertSame('10800.00', $sheet['total_assets']);
    }

    public function test_ledger_running_balance_uses_sql_window(): void
    {
        $user = User::factory()->create();
        $cash = LedgerAccounts::byCode(LedgerAccounts::CASH);
        $rent = LedgerAccounts::byCode(LedgerAccounts::RENT);

        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Payment,
            'created_by' => $user,
        ], [
            ['account_id' => $rent->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 500],
        ]);
        $this->accounting->post($entry);

        $rows = $this->reports->ledger(
            $cash->id,
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(9500.0, (float) $rows->first()->running_balance, 0.001);
    }

    public function test_daily_collection_and_month_wise_are_sql_aggregates(): void
    {
        $user = User::factory()->create();
        $cash = LedgerAccounts::byCode(LedgerAccounts::CASH);
        $income = LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME);

        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Receipt,
            'created_by' => $user,
        ], [
            ['account_id' => $cash->id, 'debit' => 750, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 750],
        ]);
        $this->accounting->post($entry);

        $daily = $this->reports->dailyCollection(
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );

        $this->assertTrue($daily->contains(fn ($row) => (float) $row->total === 750.0));

        $monthly = $this->reports->monthWiseCollection((int) now()->year);
        $this->assertTrue($monthly->contains(fn ($row) => (float) $row->total >= 750.0));
    }

    public function test_rebuild_daily_balances_command_upserts_summary_rows(): void
    {
        $user = User::factory()->create();
        $cash = LedgerAccounts::byCode(LedgerAccounts::CASH);
        $rent = LedgerAccounts::byCode(LedgerAccounts::RENT);

        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Payment,
            'created_by' => $user,
        ], [
            ['account_id' => $rent->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100],
        ]);
        $this->accounting->post($entry);

        Artisan::call('accounting:rebuild-daily-balances');

        $this->assertTrue(
            AccountBalanceDaily::query()
                ->where('account_id', $cash->id)
                ->whereDate('date', now()->toDateString())
                ->exists()
        );

        $this->assertTrue(app(AccountBalanceService::class)->dailySummaryAvailable());
    }
}
