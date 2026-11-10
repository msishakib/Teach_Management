<?php

namespace Tests\Feature;

use App\Enums\JournalEntryStatus;
use App\Enums\RoleName;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\LedgerAccounts;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accounting;

    protected Account $cash;

    protected Account $bank;

    protected Account $capital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->accounting = app(AccountingService::class);
        $this->cash = Account::query()->where('code', LedgerAccounts::CASH)->firstOrFail();
        $this->bank = Account::query()->where('code', LedgerAccounts::BANK)->firstOrFail();
        $this->capital = Account::query()->where('code', '3100')->firstOrFail();
    }

    public function test_create_and_post_journal_voucher(): void
    {
        $user = $this->admin();

        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
            'narration' => 'Owner capital introduced',
            'created_by' => $user,
        ], [
            ['account_id' => $this->cash->id, 'debit' => 5000, 'credit' => 0],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $this->assertTrue($entry->isDraft());
        $this->assertStringStartsWith('JV-', $entry->entry_no);

        $posted = $this->accounting->post($entry);

        $this->assertSame(JournalEntryStatus::Posted, $posted->status);
        $this->assertNotNull($posted->posted_at);
    }

    public function test_update_draft_journal_voucher_replaces_lines(): void
    {
        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
            'narration' => 'Draft transfer',
        ], [
            ['account_id' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->bank->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $updated = $this->accounting->updateDraft($entry, [
            'entry_date' => now()->toDateString(),
            'narration' => 'Updated transfer',
        ], [
            ['account_id' => $this->bank->id, 'debit' => 2000, 'credit' => 0],
            ['account_id' => $this->cash->id, 'debit' => 0, 'credit' => 2000],
        ]);

        $this->assertSame('Updated transfer', $updated->narration);
        $this->assertSame(2, $updated->lines()->count());
        $this->assertSame(
            '2000.00',
            number_format((float) $updated->lines->where('account_id', $this->bank->id)->sum('debit'), 2, '.', '')
        );
    }

    public function test_update_draft_rejects_posted_entry(): void
    {
        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
        ], [
            ['account_id' => $this->cash->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 100],
        ]);

        $this->accounting->post($entry);

        $this->expectException(ValidationException::class);

        $this->accounting->updateDraft($entry->fresh(), [
            'narration' => 'Nope',
        ], [
            ['account_id' => $this->cash->id, 'debit' => 50, 'credit' => 0],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 50],
        ]);
    }

    public function test_reverse_posted_journal_voucher(): void
    {
        $entry = $this->accounting->createEntry([
            'entry_date' => now()->toDateString(),
            'voucher_type' => VoucherType::Journal,
        ], [
            ['account_id' => $this->cash->id, 'debit' => 300, 'credit' => 0],
            ['account_id' => $this->capital->id, 'debit' => 0, 'credit' => 300],
        ]);

        $posted = $this->accounting->post($entry);
        $reversal = $this->accounting->reverse($posted, $this->admin());

        $this->assertTrue($reversal->isPosted());
        $this->assertSame(JournalEntryStatus::Reversed, $posted->fresh()->status);
        $this->assertSame(
            '300.00',
            number_format((float) $reversal->lines->where('account_id', $this->cash->id)->sum('credit'), 2, '.', '')
        );
    }

    protected function admin(): User
    {
        $user = User::factory()->admin()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }
}
