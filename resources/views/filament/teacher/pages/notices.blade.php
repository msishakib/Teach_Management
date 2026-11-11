<x-filament-panels::page>
    <div class="space-y-4">
        @forelse ($this->notices as $notice)
            <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h3 class="text-base font-semibold">{{ $notice->title }}</h3>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $notice->published_at?->format('d M Y H:i') }}
                    @if ($notice->batch)
                        · {{ $notice->batch->name }}
                    @endif
                </p>
                <div class="mt-3 whitespace-pre-line text-sm">{{ $notice->body }}</div>
            </article>
        @empty
            <p class="text-sm text-gray-500">No notices yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $this->notices->links() }}</div>
</x-filament-panels::page>
