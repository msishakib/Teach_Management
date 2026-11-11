<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Pending marks</x-slot>
        <x-slot name="description">
            {{ number_format($summary['pending_subjects_count']) }} subject(s) · {{ number_format($summary['pending_marks_count']) }} left
        </x-slot>

        @if (empty($summary['items']))
            <p class="text-xs text-gray-500 dark:text-gray-400">No pending marks.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($summary['items'] as $item)
                    <li class="flex items-center justify-between gap-2 py-1.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $item['exam_name'] }} — {{ $item['subject_name'] }}</p>
                            <p class="truncate text-[0.7rem] text-gray-500 dark:text-gray-400">
                                {{ $item['batch_name'] }} · {{ \Illuminate\Support\Carbon::parse($item['exam_date'])->format('d M') }}
                            </p>
                        </div>
                        <x-filament::badge size="sm" color="warning">
                            {{ $item['completed_count'] }}/{{ $item['enrolled_count'] }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
