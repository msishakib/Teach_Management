<?php

namespace App\Filament\Resources\MediaResource\Widgets;

use App\Services\Media\MediaQuotaService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MediaStorageChart extends ChartWidget
{
    protected static ?string $heading = 'Storage usage by collection';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '240px';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null
            && ($user->can('media.view') || $user->can('media.view_private') || $user->can('media.delete'));
    }

    public function getDescription(): ?string
    {
        $total = app(MediaQuotaService::class)->totalUsageBytes();

        return 'Total stored: '.number_format($total / 1048576, 2).' MB';
    }

    protected function getData(): array
    {
        $summary = app(MediaQuotaService::class)->summary();

        $labels = [];
        $data = [];

        foreach ($summary as $row) {
            if ($row['usage_bytes'] <= 0) {
                continue;
            }

            $labels[] = $row['label'];
            $data[] = round($row['usage_bytes'] / 1048576, 2);
        }

        if ($data === []) {
            $labels = ['No media yet'];
            $data = [1];
        }

        return [
            'datasets' => [
                [
                    'label' => 'MB',
                    'data' => $data,
                    'backgroundColor' => [
                        '#4f46e5', '#0ea5e9', '#10b981', '#f59e0b',
                        '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'right', 'labels' => ['boxWidth' => 12, 'font' => ['size' => 11]]],
            ],
        ];
    }
}
