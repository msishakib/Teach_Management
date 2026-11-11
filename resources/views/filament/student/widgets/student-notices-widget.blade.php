<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">Notices</x-slot>
        <x-slot name="description">{{ number_format($summary['total_count']) }} available</x-slot>

        @if (empty($summary['items']))
            <p class="text-xs text-gray-500 dark:text-gray-400">No notices yet.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($summary['items'] as $notice)
                    <li class="py-1.5 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-medium text-gray-950 dark:text-white">
                                @if ($notice['is_pinned'])
                                    <span class="text-amber-600 dark:text-amber-400">●</span>
                                @endif
                                {{ $notice['title'] }}
                            </p>
                            <span class="shrink-0 text-[0.7rem] text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Carbon::parse($notice['published_at'])->format('d M') }}
                            </span>
                        </div>
                        <p class="mt-0.5 line-clamp-1 text-[0.7rem] text-gray-500 dark:text-gray-400">
                            {{ \Illuminate\Support\Str::limit(strip_tags($notice['body']), 100) }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
