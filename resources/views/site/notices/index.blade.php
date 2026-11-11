<x-layouts.site :title="__('site.notices.title')" :description="__('site.notices.subtitle')">
    <x-section id="notices-all" :eyebrow="__('site.notices.eyebrow')" :title="__('site.notices.title')" :subtitle="__('site.notices.subtitle')">
        @if ($notices->isEmpty())
            <p class="glass mx-auto max-w-xl rounded-3xl p-10 text-center text-muted">{{ __('site.notices.empty') }}</p>
        @else
            <ul class="mx-auto grid max-w-4xl gap-3" role="list">
                @foreach ($notices as $notice)
                    <li data-reveal style="--d: {{ min($loop->index, 6) * 50 }}ms">
                        <a href="{{ route('notices.show', ['notice' => $notice->id, 'slug' => $notice->slug()]) }}"
                           class="glass group flex items-center gap-4 rounded-2xl p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-lift sm:gap-5 sm:p-5">
                            <time datetime="{{ $notice->published_at->toDateString() }}" class="grid size-16 shrink-0 place-items-center rounded-2xl bg-primary/10 text-center leading-tight text-primary-ink">
                                <span>
                                    <span class="block text-xl font-extrabold">{{ site_date($notice->published_at, 'd') }}</span>
                                    <span class="block text-[0.7rem] font-bold uppercase tracking-wider">{{ site_date($notice->published_at, 'MMM') }}</span>
                                </span>
                            </time>
                            <div class="min-w-0 flex-1">
                                <h2 class="font-bold leading-snug">{{ $notice->title }}</h2>
                                <p class="mt-1 line-clamp-2 text-sm text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($notice->body), 160) }}</p>
                            </div>
                            <x-heroicon-o-arrow-up-right class="size-5 shrink-0 text-muted transition group-hover:text-primary-ink" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mx-auto mt-10 max-w-4xl">{{ $notices->links('site.pagination') }}</div>
        @endif
    </x-section>
</x-layouts.site>
