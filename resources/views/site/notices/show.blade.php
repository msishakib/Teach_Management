<x-layouts.site :title="$notice->title" :description="\Illuminate\Support\Str::limit(strip_tags($notice->body), 160)" type="article">
    <section class="py-12 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <a href="{{ route('notices.index') }}" class="mb-8 inline-flex items-center gap-2 text-sm font-semibold text-muted transition-colors hover:text-primary-ink">
                <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" /> {{ __('site.notices.back') }}
            </a>

            <article data-reveal class="glass-strong rounded-[2rem] p-6 shadow-soft sm:p-12">
                <p class="eyebrow mb-5">
                    <x-heroicon-o-calendar-days class="size-4" aria-hidden="true" />
                    <time datetime="{{ $notice->published_at->toDateString() }}">{{ site_date($notice->published_at, 'd MMMM y') }}</time>
                </p>
                <h1 class="text-balance text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">{{ $notice->title }}</h1>
                <div class="prose-site mt-8 text-lg">{{ $notice->body }}</div>
            </article>

            @if ($related->isNotEmpty())
                <h2 class="mb-4 mt-14 text-xl font-extrabold tracking-tight">{{ __('site.notices.more') }}</h2>
                <ul class="grid gap-3" role="list">
                    @foreach ($related as $item)
                        <li>
                            <a href="{{ route('notices.show', ['notice' => $item->id, 'slug' => $item->slug()]) }}" class="glass flex items-center justify-between gap-4 rounded-2xl p-4 transition hover:-translate-y-0.5 hover:shadow-lift">
                                <span class="font-semibold">{{ $item->title }}</span>
                                <time class="shrink-0 text-sm text-muted" datetime="{{ $item->published_at->toDateString() }}">{{ site_date($item->published_at) }}</time>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-layouts.site>
