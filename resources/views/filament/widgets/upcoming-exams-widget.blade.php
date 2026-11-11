<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Upcoming exams</x-slot>
        <x-slot name="description">
            {{ number_format($summary['upcoming_count']) }} total · {{ number_format($summary['scheduled_count']) }} scheduled · {{ number_format($summary['ongoing_count']) }} ongoing
        </x-slot>

        @if (empty($summary['items']))
            <p class="text-xs text-gray-500 dark:text-gray-400">No upcoming exams.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($summary['items'] as $exam)
                    <li class="flex items-center justify-between gap-2 py-1.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $exam['name'] }}</p>
                            <p class="truncate text-[0.7rem] text-gray-500 dark:text-gray-400">
                                {{ $exam['batch_name'] }} · {{ \Illuminate\Support\Carbon::parse($exam['start_date'])->format('d M') }} · {{ $exam['subjects_count'] }} subj.
                            </p>
                        </div>
                        <x-filament::badge size="sm" :color="$exam['status'] === 'ongoing' ? 'warning' : 'info'">
                            {{ ucfirst($exam['status']) }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
