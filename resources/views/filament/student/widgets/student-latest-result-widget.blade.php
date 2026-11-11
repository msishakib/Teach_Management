<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Latest result</x-slot>
        <x-slot name="description">{{ number_format($summary['published_count']) }} published · GPA {{ $summary['avg_gpa'] }}</x-slot>

        @if (! $summary['latest'])
            <p class="text-xs text-gray-500 dark:text-gray-400">No published results.</p>
        @else
            @php($latest = $summary['latest'])
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 space-y-0.5">
                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $latest['exam_name'] }}</p>
                    <p class="truncate text-[0.7rem] text-gray-500 dark:text-gray-400">
                        {{ $latest['student_name'] }} · {{ $latest['total_marks'] }}/{{ $latest['full_marks'] }} ({{ $latest['percentage'] }}%)
                        @if ($latest['rank_in_batch']) · #{{ $latest['rank_in_batch'] }} @endif
                    </p>
                </div>
                <x-filament::badge size="sm" :color="$latest['status'] === 'pass' ? 'success' : 'danger'">
                    {{ $latest['gpa'] }}{{ $latest['letter_grade'] ? ' '.$latest['letter_grade'] : '' }}
                </x-filament::badge>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
