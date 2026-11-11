<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Pending attendance</x-slot>
        <x-slot name="description">
            {{ number_format($summary['pending_today_count']) }} today · {{ number_format($summary['open_sessions_count']) }} open
        </x-slot>

        @if (empty($summary['items']))
            <p class="text-xs text-gray-500 dark:text-gray-400">All attendance finalized.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($summary['items'] as $item)
                    <li class="flex items-center justify-between gap-2 py-1.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $item['batch_name'] }} — {{ $item['subject_name'] }}</p>
                            <p class="text-[0.7rem] text-gray-500 dark:text-gray-400">{{ $item['start_time'] }}</p>
                        </div>
                        <x-filament::badge size="sm" :color="$item['state'] === 'open' ? 'warning' : 'danger'">
                            {{ $item['state'] === 'open' ? 'Open' : 'Missing' }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
