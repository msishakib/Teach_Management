<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            [
                'code' => '1000',
                'name' => 'Assets',
                'type' => AccountType::Asset,
                'is_group' => true,
                'children' => [
                    [
                        'code' => '1100',
                        'name' => 'Current Assets',
                        'type' => AccountType::Asset,
                        'is_group' => true,
                        'children' => [
                            [
                                'code' => '1110',
                                'name' => 'Cash',
                                'type' => AccountType::Asset,
                                'is_cash_or_bank' => true,
                            ],
                            [
                                'code' => '1120',
                                'name' => 'Bank',
                                'type' => AccountType::Asset,
                                'is_cash_or_bank' => true,
                            ],
                            [
                                'code' => '1130',
                                'name' => 'bKash',
                                'type' => AccountType::Asset,
                                'is_cash_or_bank' => true,
                            ],
                            [
                                'code' => '1135',
                                'name' => 'Nagad',
                                'type' => AccountType::Asset,
                                'is_cash_or_bank' => true,
                            ],
                            [
                                'code' => '1140',
                                'name' => 'Student Receivable',
                                'type' => AccountType::Asset,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'code' => '2000',
                'name' => 'Liabilities',
                'type' => AccountType::Liability,
                'is_group' => true,
                'children' => [
                    [
                        'code' => '2100',
                        'name' => 'Advance from Students',
                        'type' => AccountType::Liability,
                    ],
                ],
            ],
            [
                'code' => '3000',
                'name' => 'Equity',
                'type' => AccountType::Equity,
                'is_group' => true,
                'children' => [
                    [
                        'code' => '3100',
                        'name' => 'Capital',
                        'type' => AccountType::Equity,
                    ],
                ],
            ],
            [
                'code' => '4000',
                'name' => 'Income',
                'type' => AccountType::Income,
                'is_group' => true,
                'children' => [
                    [
                        'code' => '4100',
                        'name' => 'Tuition Fee Income',
                        'type' => AccountType::Income,
                    ],
                    [
                        'code' => '4200',
                        'name' => 'Admission Fee Income',
                        'type' => AccountType::Income,
                    ],
                    [
                        'code' => '4300',
                        'name' => 'Exam Fee Income',
                        'type' => AccountType::Income,
                    ],
                ],
            ],
            [
                'code' => '5000',
                'name' => 'Expenses',
                'type' => AccountType::Expense,
                'is_group' => true,
                'children' => [
                    [
                        'code' => '5100',
                        'name' => 'Discount Allowed',
                        'type' => AccountType::Expense,
                    ],
                    [
                        'code' => '5200',
                        'name' => 'Salary Expense',
                        'type' => AccountType::Expense,
                    ],
                    [
                        'code' => '5300',
                        'name' => 'Rent',
                        'type' => AccountType::Expense,
                    ],
                    [
                        'code' => '5400',
                        'name' => 'Utility',
                        'type' => AccountType::Expense,
                    ],
                ],
            ],
        ];

        foreach ($tree as $node) {
            $this->seedNode($node);
        }

        $cash = Account::query()->where('code', '1110')->first();
        $bank = Account::query()->where('code', '1120')->first();

        if ($cash || $bank) {
            app(SettingsService::class)->setMany(array_filter([
                'finance.default_cash_account' => $cash?->id,
                'finance.default_bank_account' => $bank?->id,
            ], fn ($value) => $value !== null));
        }
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     type: AccountType,
     *     is_group?: bool,
     *     is_cash_or_bank?: bool,
     *     opening_balance?: float|int|string,
     *     children?: list<array<string, mixed>>
     * }  $node
     */
    protected function seedNode(array $node, ?Account $parent = null): Account
    {
        $account = Account::query()->updateOrCreate(
            ['code' => $node['code']],
            [
                'parent_id' => $parent?->id,
                'name' => $node['name'],
                'type' => $node['type'],
                'is_group' => (bool) ($node['is_group'] ?? false),
                'is_cash_or_bank' => (bool) ($node['is_cash_or_bank'] ?? false),
                'opening_balance' => $node['opening_balance'] ?? 0,
                'status' => AccountStatus::Active,
            ],
        );

        foreach ($node['children'] ?? [] as $child) {
            $this->seedNode($child, $account);
        }

        return $account;
    }
}
