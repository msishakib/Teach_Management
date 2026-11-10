<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\FinancialReportExportReady;
use App\Services\FinancialReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportFinancialReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $userId,
        public string $reportType,
        public string $format,
        public array $filters = [],
    ) {}

    public function handle(FinancialReportExportService $exports): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $path = $exports->export($this->reportType, $this->format, $this->filters);

        $user->notify(new FinancialReportExportReady($path, $this->reportType, $this->format));
    }
}
