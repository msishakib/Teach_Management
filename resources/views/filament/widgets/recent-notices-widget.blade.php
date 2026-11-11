<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Recent notices</x-slot>
        <x-slot name="description">
            {{ number_format($summary['active_count']) }} active · {{ number_format($summary['pinned_count']) }} pinned
        </x-slot>

        @if (empty($summary['items']))
            <p class="text-xs text-gray-500 dark:text-gray-400">No active notices.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($summary['items'] as $notice)
                    <li class="flex items-center justify-between gap-2 py-1.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                @if ($notice['is_pinned'])
                                    <span class="text-amber-600 dark:text-amber-400">●</span>
                                @endif
                                {{ $notice['title'] }}
                            </p>
                            <p class="truncate text-[0.7rem] text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Carbon::parse($notice['published_at'])->format('d M Y') }}
                                @if ($notice['batch_name']) · {{ $notice['batch_name'] }} @endif
                            </p>
                        </div>
                        <x-filament::badge size="sm" color="gray">
                            {{ ucfirst($notice['audience']) }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
