<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Backup\BackupDestination\BackupDestination;

class HealthCheckService
{
    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'queue' => $this->queueStatus(),
            'backups' => $this->backupStatus(),
            'disk' => $this->diskStatus(),
            'failed_jobs' => $this->failedJobs(),
        ];
    }

    /**
     * @return array{connection: string, pending: int|null, table_exists: bool}
     */
    public function queueStatus(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $tableExists = $table !== '' && Schema::hasTable($table);

        return [
            'connection' => $connection,
            'table_exists' => $tableExists,
            'pending' => $tableExists ? (int) DB::table($table)->count() : null,
        ];
    }

    /**
     * @return list<array{name: string, disk: string, reachable: bool, count: int, newest_at: string|null, newest_age_hours: float|null, used_bytes: float|null, error: string|null}>
     */
    public function backupStatus(): array
    {
        $name = (string) config('backup.backup.name', config('app.name'));
        $disks = (array) config('backup.backup.destination.disks', ['local']);

        return collect($disks)
            ->filter(fn ($disk) => is_string($disk) && $disk !== '')
            ->map(function (string $disk) use ($name): array {
                $destination = BackupDestination::create($disk, $name);
                $reachable = $destination->isReachable();
                $backups = $reachable ? $destination->backups() : collect();
                $newest = $backups instanceof \Illuminate\Support\Collection ? $backups->first() : $destination->newestBackup();

                $newestAt = $newest?->date();
                $ageHours = $newestAt instanceof Carbon ? round($newestAt->diffInMinutes(now()) / 60, 1) : null;

                return [
                    'name' => $name,
                    'disk' => $disk,
                    'reachable' => $reachable,
                    'count' => $backups instanceof \Illuminate\Support\Collection ? $backups->count() : ($reachable ? $destination->backups()->count() : 0),
                    'newest_at' => $newestAt?->toDateTimeString(),
                    'newest_age_hours' => $ageHours,
                    'used_bytes' => $reachable ? (float) $destination->usedStorage() : null,
                    'error' => $destination->connectionError?->getMessage(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{path: string, free_bytes: int|float|null, total_bytes: int|float|null, free_percent: float|null}
     */
    public function diskStatus(): array
    {
        $path = base_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        return [
            'path' => $path,
            'free_bytes' => $free === false ? null : $free,
            'total_bytes' => $total === false ? null : $total,
            'free_percent' => ($free !== false && $total !== false && $total > 0)
                ? round(($free / $total) * 100, 1)
                : null,
        ];
    }

    /**
     * @return array{table_exists: bool, count: int|null, latest_failed_at: string|null}
     */
    public function failedJobs(): array
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');
        $exists = Schema::hasTable($table);

        if (! $exists) {
            return [
                'table_exists' => false,
                'count' => null,
                'latest_failed_at' => null,
            ];
        }

        return [
            'table_exists' => true,
            'count' => (int) DB::table($table)->count(),
            'latest_failed_at' => DB::table($table)->max('failed_at'),
        ];
    }
}
