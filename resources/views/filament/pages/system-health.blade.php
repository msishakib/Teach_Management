<x-filament-panels::page>
    @php
        $queue = $report['queue'] ?? [];
        $backups = $report['backups'] ?? [];
        $disk = $report['disk'] ?? [];
        $failed = $report['failed_jobs'] ?? [];

        $formatBytes = function ($bytes): string {
            if ($bytes === null || $bytes === '') {
                return '—';
            }

            $bytes = (float) $bytes;
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = 0;

            while ($bytes >= 1024 && $i < count($units) - 1) {
                $bytes /= 1024;
                $i++;
            }

            return number_format($bytes, $i === 0 ? 0 : 2).' '.$units[$i];
        };
    @endphp

    <div class="space-y-6">
        <div class="flex justify-end">
            <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="refreshReport">
                Refresh
            </x-filament::button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-filament::section heading="Queue status" icon="heroicon-o-queue-list">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Connection</dt>
                        <dd class="font-medium">{{ $queue['connection'] ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Pending jobs</dt>
                        <dd class="font-medium">
                            @if (($queue['table_exists'] ?? false) === false)
                                <x-filament::badge color="warning">No jobs table</x-filament::badge>
                            @elseif (($queue['connection'] ?? '') === 'sync')
                                <x-filament::badge color="gray">Sync driver</x-filament::badge>
                            @else
                                {{ $queue['pending'] ?? '—' }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Disk space" icon="heroicon-o-server-stack">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Path</dt>
                        <dd class="max-w-[14rem] truncate font-medium" title="{{ $disk['path'] ?? '' }}">{{ $disk['path'] ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Free</dt>
                        <dd class="font-medium">
                            {{ $formatBytes($disk['free_bytes'] ?? null) }}
                            @if (($disk['free_percent'] ?? null) !== null)
                                <span class="text-gray-500">({{ $disk['free_percent'] }}%)</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Total</dt>
                        <dd class="font-medium">{{ $formatBytes($disk['total_bytes'] ?? null) }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Failed jobs" icon="heroicon-o-exclamation-triangle">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Count</dt>
                        <dd>
                            @if (($failed['table_exists'] ?? false) === false)
                                <x-filament::badge color="warning">No table</x-filament::badge>
                            @elseif (($failed['count'] ?? 0) > 0)
                                <x-filament::badge color="danger">{{ $failed['count'] }}</x-filament::badge>
                            @else
                                <x-filament::badge color="success">0</x-filament::badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Latest failure</dt>
                        <dd class="font-medium">{{ $failed['latest_failed_at'] ?? '—' }}</dd>
                    </div>
                </dl>
            </x-filament::section>
        </div>

        <x-filament::section heading="Backups" icon="heroicon-o-archive-box">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">Disk</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Backups</th>
                            <th class="py-2 pr-4">Last backup</th>
                            <th class="py-2 pr-4">Age</th>
                            <th class="py-2">Used</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($backups as $backup)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $backup['disk'] }}</td>
                                <td class="py-3 pr-4">
                                    @if ($backup['reachable'])
                                        <x-filament::badge color="success">Reachable</x-filament::badge>
                                    @else
                                        <x-filament::badge color="danger">Unreachable</x-filament::badge>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">{{ $backup['count'] }}</td>
                                <td class="py-3 pr-4">{{ $backup['newest_at'] ?? '—' }}</td>
                                <td class="py-3 pr-4">
                                    @if (($backup['newest_age_hours'] ?? null) !== null)
                                        {{ $backup['newest_age_hours'] }} h
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3">{{ $formatBytes($backup['used_bytes'] ?? null) }}</td>
                            </tr>
                            @if (! empty($backup['error']))
                                <tr>
                                    <td colspan="6" class="pb-3 text-xs text-danger-600">{{ $backup['error'] }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-gray-500">No backup destinations configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
