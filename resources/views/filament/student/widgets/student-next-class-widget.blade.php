<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Next class</x-slot>
        <x-slot name="description">{{ number_format($summary['weekly_classes_count']) }}/week</x-slot>

        @if (! $summary['next_class'])
            <p class="text-xs text-gray-500 dark:text-gray-400">No upcoming classes.</p>
        @else
            @php($next = $summary['next_class'])
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 space-y-0.5">
                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $next['subject_name'] }}</p>
                    <p class="truncate text-[0.7rem] text-gray-500 dark:text-gray-400">{{ $next['batch_name'] }} · {{ $next['teacher_name'] }}</p>
                    <p class="text-[0.7rem] font-medium text-gray-700 dark:text-gray-300">
                        {{ $next['day_label'] }} · {{ $next['start_time'] }}–{{ $next['end_time'] }}
                    </p>
                </div>
                <x-filament::badge size="sm" :color="$next['is_today'] ? 'success' : 'info'">
                    {{ $next['is_today'] ? 'Today' : $next['day_label'] }}
                </x-filament::badge>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
