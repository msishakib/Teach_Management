<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accounting;

    protected Account $cash;

    protected Account $tuitionIncome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->accounting = app(AccountingService::class);
        $this->cash = Account::query()->where('code', '1110')->firstOrFail();
        $this->tuitionIncome = Account::query()->where('code', '4100')->firstOrFail();
    }

    public function test_chart_of_accounts_seeder_creates_standard_accounts(): void
    {
        $expected = [
            '1110' => 'Cash',
            '1120' => 'Bank',
            '1130' => 'bKash',
            '1140' => 'Student Receivable',
            '2100' => 'Advance from Students',
            '3100' => 'Capital',
            '4100' => 'Tuition Fee Income',
            '4200' => 'Admission Fee Income',
            '4300' => 'Exam Fee Income',
            '5100' => 'Discount Allowed',
            '5200' => 'Salary Expense',
            '5300' => 'Rent',
            '5400' => 'Utility',
        ];

        foreach ($expected as $code => $name) {
            $account = Account::query()->where('code', $code)->first();
            $this->assertNotNull($account, "Missing account {$code}");
            $this->assertSame($name, $account->name);
            $this->assertFalse($account->is_group);
        }

        $this->assertTrue(Account::query()->where('code', '1110')->value('is_cash_or_bank'));
        $this->assertTrue(Account::query()->where('code', '1120')->value('is_cash_or_bank'));
        $this->assertTrue(Account::query()->where('code', '1130')->value('is_cash_or_bank'));
        $this->assertSame(AccountType::Income, $this->tuitionIncome->type);
    }

    public function test_create_entry_persists_balanced_draft(): void
    {
        $user = User::factory()->create();

        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Receipt,
            'narration' => 'Tuition collected',
            'created_by' => $user,
        ], [
            [
                'account_id' => $this->cash->id,
                'debit' => 1500.50,
                'credit' => 0,
                'description' => 'Cash received',
            ],
            [
                'account_id' => $this->tuitionIncome->id,
                'debit' => 0,
                'credit' => 1500.50,
                'description' => 'Tuition income',
            ],
        ]);

        $this->assertTrue($entry->isDraft());
        $this->assertNull($entry->posted_at);
        $this->assertSame(2, $entry->lines()->count());
        $this->assertSame('1500.50', number_format((float) $entry->lines()->sum('debit'), 2, '.', ''));
        $this->assertSame('1500.50', number_format((float) $entry->lines()->sum('credit'), 2, '.', ''));
        $this->assertStringStartsWith('RCPT-', $entry->entry_no);
    }

    public function test_create_entry_rejects_unbalanced_lines(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->accounting->createEntry([
                'entry_date' => now()->toDateString(),
                'voucher_type' => VoucherType::Journal,
            ], [
                [
                    'account_id' => $this->cash->id,
                    'debit' => 1000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->tuitionIncome->id,
                    'debit' => 0,
                    'credit' => 900,
                ],
            ]);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
            $this->assertSame(0, JournalEntry::query()->count());
            throw $exception;
        }
    }

    public function test_create_entry_requires_at_least_two_lines(): void
    {
        $this->expectException(ValidationException::class);

        $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
        ], [
            [
                'account_id' => $this->cash->id,
                'debit' => 500,
                'credit' => 0,
            ],
        ]);
    }

    public function test_create_entry_rejects_group_accounts(): void
    {
        $assets = Account::query()->where('code', '1000')->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
        ], [
            [
                'account_id' => $assets->id,
                'debit' => 100,
                'credit' => 0,
            ],
            [
                'account_id' => $this->tuitionIncome->id,
                'debit' => 0,
                'credit' => 100,
            ],
        ]);
    }

    public function test_post_marks_draft_as_posted_and_immutable(): void
    {
        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
            'narration' => 'Balanced journal',
        ], $this->balancedLines(250));

        $posted = $this->accounting->post($entry);

        $this->assertTrue($posted->isPosted());
        $this->assertNotNull($posted->posted_at);
        $this->assertSame(JournalEntryStatus::Posted, $posted->status);

        $this->expectException(LogicException::class);
        $posted->update(['narration' => 'Tampered']);
    }

    public function test_reverse_creates_offsetting_posted_entry_and_locks_original(): void
    {
        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Payment,
            'narration' => 'Rent paid',
        ], [
            [
                'account_id' => Account::query()->where('code', '5300')->value('id'),
                'debit' => 800,
                'credit' => 0,
            ],
            [
                'account_id' => $this->cash->id,
                'debit' => 0,
                'credit' => 800,
            ],
        ]);

        $posted = $this->accounting->post($entry);
        $reversal = $this->accounting->reverse($posted);

        $posted->refresh();

        $this->assertTrue($posted->isReversed());
        $this->assertTrue($reversal->isPosted());
        $this->assertSame(JournalEntry::class, $reversal->reference_type);
        $this->assertSame($posted->id, $reversal->reference_id);
        $this->assertStringContainsString($posted->entry_no, (string) $reversal->narration);

        $originalDebit = (string) $posted->lines()->sum('debit');
        $reversalCredit = (string) $reversal->lines()->sum('credit');
        $originalCredit = (string) $posted->lines()->sum('credit');
        $reversalDebit = (string) $reversal->lines()->sum('debit');

        $this->assertSame($originalDebit, $reversalCredit);
        $this->assertSame($originalCredit, $reversalDebit);

        $this->expectException(LogicException::class);
        $posted->update(['narration' => 'Should fail']);
    }

    public function test_reverse_rejects_draft_entries(): void
    {
        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
        ], $this->balancedLines(100));

        $this->expectException(ValidationException::class);
        $this->accounting->reverse($entry);
    }

    public function test_post_rejects_already_posted_entries(): void
    {
        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
        ], $this->balancedLines(100));

        $this->accounting->post($entry);

        $this->expectException(ValidationException::class);
        $this->accounting->post($entry->fresh());
    }

    /**
     * @return list<array{account_id: int, debit: float|int, credit: float|int}>
     */
    protected function balancedLines(float|int $amount): array
    {
        return [
            [
                'account_id' => $this->cash->id,
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $this->tuitionIncome->id,
                'debit' => 0,
                'credit' => $amount,
            ],
        ];
    }
}
