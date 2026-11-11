<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Today's classes</x-slot>
        <x-slot name="description">
            {{ number_format($summary['classes_count']) }} class(es) · {{ number_format($summary['batches_count']) }} batch(es)
            @if ($summary['first_start'] && $summary['last_end'])
                · {{ $summary['first_start'] }}–{{ $summary['last_end'] }}
            @endif
        </x-slot>

        @if (empty($summary['items']))
            <p class="text-xs text-gray-500 dark:text-gray-400">No classes today.</p>
        @else
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($summary['items'] as $slot)
                    <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $slot['subject_name'] }}</span>
                            <span class="shrink-0 text-[0.7rem] font-medium text-primary-600 dark:text-primary-400">
                                {{ $slot['start_time'] }}–{{ $slot['end_time'] }}
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-[0.7rem] text-gray-500 dark:text-gray-400">
                            {{ $slot['batch_name'] }} · {{ number_format($slot['student_count']) }} students
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
