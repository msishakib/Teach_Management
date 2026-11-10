<?php

namespace App\Console\Commands;

use App\Services\AccountBalanceService;
use Illuminate\Console\Command;

class RebuildAccountBalancesDailyCommand extends Command
{
    protected $signature = 'accounting:rebuild-daily-balances {--from=} {--to=}';

    protected $description = 'Rebuild the account_balances_daily summary table from posted journal lines';

    public function handle(AccountBalanceService $balances): int
    {
        $count = $balances->rebuildDailyBalances(
            $this->option('from') ?: null,
            $this->option('to') ?: null,
        );

        $this->info("Upserted {$count} account/day balance rows.");

        return self::SUCCESS;
    }
}
