<?php

namespace Database\Seeders;

use App\Models\FeeType;
use App\Support\LedgerAccounts;
use Illuminate\Database\Seeder;

class FeeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'TUITION',
                'name' => 'Tuition Fee',
                'income_account_id' => LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME)->id,
            ],
            [
                'code' => 'ADMISSION',
                'name' => 'Admission Fee',
                'income_account_id' => LedgerAccounts::byCode(LedgerAccounts::ADMISSION_FEE_INCOME)->id,
            ],
            [
                'code' => 'EXAM',
                'name' => 'Exam Fee',
                'income_account_id' => LedgerAccounts::byCode(LedgerAccounts::EXAM_FEE_INCOME)->id,
            ],
        ];

        foreach ($types as $type) {
            FeeType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'income_account_id' => $type['income_account_id'],
                    'is_active' => true,
                ],
            );
        }
    }
}
