<?php

namespace App\Support;

use App\Models\Account;
use RuntimeException;

final class LedgerAccounts
{
    public const CASH = '1110';

    public const BANK = '1120';

    public const BKASH = '1130';

    public const NAGAD = '1135';

    public const STUDENT_RECEIVABLE = '1140';

    public const ADVANCE_FROM_STUDENTS = '2100';

    public const TUITION_FEE_INCOME = '4100';

    public const ADMISSION_FEE_INCOME = '4200';

    public const EXAM_FEE_INCOME = '4300';

    public const DISCOUNT_ALLOWED = '5100';

    public const SALARY_EXPENSE = '5200';

    public const RENT = '5300';

    public const UTILITY = '5400';

    public static function byCode(string $code): Account
    {
        $account = Account::query()->where('code', $code)->first();

        if (! $account) {
            throw new RuntimeException("Ledger account [{$code}] is missing. Seed the chart of accounts.");
        }

        return $account;
    }
}
