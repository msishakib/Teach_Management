<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\AccountType;
use App\Models\Account;
use App\Services\AccountService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->accounts = app(AccountService::class);
    }

    public function test_create_group_sub_group_and_ledger(): void
    {
        $group = $this->accounts->create([
            'kind' => AccountKind::Group,
            'code' => '6000',
            'name' => 'Other Income',
            'type' => AccountType::Income,
        ]);

        $this->assertTrue($group->is_group);
        $this->assertNull($group->parent_id);
        $this->assertSame(AccountKind::Group, $group->kind());

        $sub = $this->accounts->create([
            'kind' => AccountKind::SubGroup,
            'parent_id' => $group->id,
            'code' => '6100',
            'name' => 'Misc Income',
            'type' => AccountType::Income,
        ]);

        $this->assertSame(AccountKind::SubGroup, $sub->kind());

        $ledger = $this->accounts->create([
            'kind' => AccountKind::Ledger,
            'parent_id' => $sub->id,
            'code' => '6110',
            'name' => 'Donation Income',
            'type' => AccountType::Income,
            'opening_balance' => 100,
        ]);

        $this->assertFalse($ledger->is_group);
        $this->assertSame(AccountKind::Ledger, $ledger->kind());
        $this->assertSame('100.00', (string) $ledger->opening_balance);
    }

    public function test_ledger_type_must_match_parent(): void
    {
        $assets = Account::query()->where('code', '1000')->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->accounts->create([
            'kind' => AccountKind::Ledger,
            'parent_id' => $assets->id,
            'code' => '1999',
            'name' => 'Wrong Type',
            'type' => AccountType::Expense,
        ]);
    }

    public function test_sub_group_requires_top_level_group(): void
    {
        $currentAssets = Account::query()->where('code', '1100')->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->accounts->create([
            'kind' => AccountKind::SubGroup,
            'parent_id' => $currentAssets->id,
            'code' => '1190',
            'name' => 'Nested Sub',
            'type' => AccountType::Asset,
        ]);
    }
}
