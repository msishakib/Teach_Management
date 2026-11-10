<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ReceiveVoucherStatus;
use App\Enums\RoleName;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\ReceiveVoucherPdfService;
use App\Services\ReceiveVoucherService;
use App\Support\LedgerAccounts;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReceiveVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected ReceiveVoucherService $vouchers;

    protected Account $cash;

    protected Account $tuition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->vouchers = app(ReceiveVoucherService::class);
        $this->cash = Account::query()->where('code', LedgerAccounts::CASH)->firstOrFail();
        $this->tuition = Account::query()->where('code', LedgerAccounts::TUITION_FEE_INCOME)->firstOrFail();
    }

    public function test_approve_posts_cash_debit_and_income_credit(): void
    {
        $approver = $this->approver();

        $voucher = $this->vouchers->create([
            'account_id' => $this->tuition->id,
            'received_to_account_id' => $this->cash->id,
            'amount' => 2500,
            'method' => PaymentMethod::Cash,
            'narration' => 'Tuition collection',
            'created_by' => $approver,
        ]);

        $this->assertTrue($voucher->isDraft());

        $posted = $this->vouchers->approve($approver, $voucher);

        $this->assertSame(ReceiveVoucherStatus::Posted, $posted->status);
        $this->assertNotNull($posted->journal_entry_id);

        $entry = JournalEntry::query()->with('lines')->findOrFail($posted->journal_entry_id);
        $this->assertTrue($entry->isPosted());
        $this->assertSame(VoucherType::Receipt, $entry->voucher_type);
        $this->assertSame(
            '2500.00',
            number_format((float) $entry->lines->where('account_id', $this->cash->id)->sum('debit'), 2, '.', '')
        );
        $this->assertSame(
            '2500.00',
            number_format((float) $entry->lines->where('account_id', $this->tuition->id)->sum('credit'), 2, '.', '')
        );

        $this->assertSame(
            '2500.00',
            app(AccountBalanceService::class)->balance($this->cash->fresh())
        );
    }

    public function test_approve_requires_voucher_approve_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('voucher.create');

        $voucher = $this->vouchers->create([
            'account_id' => $this->tuition->id,
            'received_to_account_id' => $this->cash->id,
            'amount' => 100,
            'method' => PaymentMethod::Cash,
            'created_by' => $user,
        ]);

        $this->expectException(ValidationException::class);
        $this->vouchers->approve($user, $voucher);
    }

    public function test_create_rejects_cash_as_credit_account(): void
    {
        $this->expectException(ValidationException::class);

        $this->vouchers->create([
            'account_id' => $this->cash->id,
            'received_to_account_id' => $this->cash->id,
            'amount' => 100,
            'method' => PaymentMethod::Cash,
        ]);
    }

    public function test_receive_voucher_pdf_renders(): void
    {
        $approver = $this->approver();
        $voucher = $this->vouchers->create([
            'account_id' => $this->tuition->id,
            'received_to_account_id' => $this->cash->id,
            'amount' => 300,
            'method' => PaymentMethod::Cash,
            'created_by' => $approver,
        ]);

        $response = app(ReceiveVoucherPdfService::class)->stream($voucher);

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
