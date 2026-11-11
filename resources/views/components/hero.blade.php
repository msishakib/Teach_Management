@props([
    'banners' => collect(),
    'stats' => [],
    'notice' => null,
    'topper' => null,
    'courses' => collect(),
])

@php
    // Banner rows become slides; with no banners we fall back to the default copy.
    $safeUrl = fn (?string $url) => ($url && \Illuminate\Support\Str::startsWith($url, ['/', '#', 'http://', 'https://'])) ? $url : null;

    $slides = $banners->map(fn ($banner) => [
        'title' => $banner->localized('title'),
        'subtitle' => $banner->localized('subtitle'),
        'cta_label' => $banner->localized('cta_label'),
        'cta_url' => $safeUrl($banner->cta_url),
        'image' => $banner->image,
    ])->values()->all();

    if ($slides === []) {
        $slides = [[
            'title' => __('site.hero.title'),
            'subtitle' => __('site.hero.subtitle'),
            'cta_label' => null,
            'cta_url' => null,
            'image' => null,
        ]];
    }

    // Gradient on the trailing ~40% of the headline words.
    $split = function (string $title): array {
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $cut = max(1, (int) ceil(count($words) * 0.6));

        return [implode(' ', array_slice($words, 0, $cut)), implode(' ', array_slice($words, $cut))];
    };

    $count = count($slides);
    $hasImages = collect($slides)->contains(fn ($slide) => filled($slide['image']));
@endphp

<section class="relative isolate overflow-hidden" aria-labelledby="hero-title">
    {{-- Animated gradient orbs --}}
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="orb animate-float -left-24 top-10 size-[22rem] sm:size-[34rem]" style="--orb: var(--primary)"></div>
        <div class="orb animate-float-slow -right-20 top-32 size-[20rem] sm:size-[30rem]" style="--orb: var(--accent)"></div>
        <div class="orb animate-float bottom-0 left-1/3 size-[16rem] opacity-70 sm:size-[24rem]" style="--orb: var(--primary)"></div>
    </div>

    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pb-16 pt-10 sm:px-6 sm:pt-16 lg:grid-cols-[1.08fr_0.92fr] lg:gap-8 lg:px-8 lg:pb-24 lg:pt-20"
         x-data="slider({{ $count }}, 7000)"
         @mouseenter="paused = true" @mouseleave="paused = false"
         @focusin="paused = true" @focusout="paused = false">

        {{-- Copy --}}
        <div>
            <p data-reveal class="eyebrow mb-6">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-70 motion-reduce:animate-none"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                </span>
                {{ __('site.hero.badge') }}
            </p>

            {{-- Stacked slides share one grid cell so height never jumps between slides. --}}
            <div class="grid" aria-live="off">
                @foreach ($slides as $i => $slide)
                    @php [$head, $tail] = $split($slide['title']); @endphp
                    <div class="col-start-1 row-start-1 transition-all duration-700 ease-out {{ $i === 0 ? 'visible opacity-100' : 'invisible translate-y-3 opacity-0' }}"
                         :class="{ 'visible opacity-100': i === {{ $i }}, 'invisible translate-y-3 opacity-0': i !== {{ $i }} }"
                         @if ($i > 0) aria-hidden="true" @endif
                         :aria-hidden="i === {{ $i }} ? 'false' : 'true'"
                         :inert="i !== {{ $i }}">
                        <{{ $i === 0 ? 'h1' : 'p' }} @if ($i === 0) id="hero-title" @endif
                            class="text-balance text-[2.6rem] font-extrabold leading-[1.04] tracking-tight sm:text-6xl lg:text-[4.4rem]">
                            {{ $head }}@if ($tail !== '') <span class="text-gradient">{{ $tail }}</span>@endif
                        </{{ $i === 0 ? 'h1' : 'p' }}>

                        @if ($slide['subtitle'])
                            <p class="mt-6 max-w-xl text-pretty text-lg leading-relaxed text-muted sm:text-xl">{{ $slide['subtitle'] }}</p>
                        @endif

                        <div class="mt-9 flex flex-wrap items-center gap-3">
                            <a href="{{ $slide['cta_url'] ?: route('apply') }}" class="btn btn-primary !px-7 !py-3.5 text-base">
                                {{ $slide['cta_label'] ?: __('site.hero.cta_primary') }}
                                <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                            </a>
                            <a href="#courses" class="btn btn-ghost !px-7 !py-3.5 text-base">{{ __('site.hero.cta_secondary') }}</a>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($count > 1)
                <div class="mt-8 flex items-center gap-2" role="group" aria-label="{{ __('site.hero.slides') }}">
                    @foreach ($slides as $i => $slide)
                        <button type="button" @click="go({{ $i }})" :aria-current="i === {{ $i }} ? 'true' : null"
                                aria-label="{{ __('site.hero.go_to', ['n' => site_number($i + 1)]) }}"
                                class="h-2 rounded-full transition-all duration-300"
                                :class="i === {{ $i }} ? 'w-8 bg-primary' : 'w-2 bg-primary/30 hover:bg-primary/60'"></button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Visual: glass frame + floating cards. No data-reveal: this holds the LCP image. --}}
        <div class="relative mx-auto w-full max-w-md lg:max-w-none">
            <div class="relative mx-auto aspect-[4/5] w-full max-w-md overflow-hidden rounded-[2.25rem] glass-strong p-2 shadow-lift sm:rounded-[2.75rem]">
                <div class="relative size-full overflow-hidden rounded-[1.8rem] sm:rounded-[2.3rem]">
                    @if ($hasImages)
                        @foreach ($slides as $i => $slide)
                            @if ($slide['image'])
                                <img src="{{ public_image($slide['image']) }}" alt="{{ $slide['title'] }}" width="800" height="1000"
                                     @if ($i === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif
                                     decoding="async"
                                     class="absolute inset-0 size-full object-cover transition-opacity duration-700 {{ $i === 0 ? 'opacity-100' : 'opacity-0' }}"
                                     :class="{ 'opacity-100': i === {{ $i }}, 'opacity-0': i !== {{ $i }} }">
                            @endif
                        @endforeach
                        <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-transparent" aria-hidden="true"></div>
                    @else
                        <div class="grad-bg relative grid size-full place-items-center overflow-hidden">
                            <div class="absolute -right-16 -top-16 size-64 rounded-full bg-white/15" aria-hidden="true"></div>
                            <div class="absolute -bottom-24 -left-10 size-72 rounded-full bg-white/10" aria-hidden="true"></div>
                            <x-heroicon-o-academic-cap class="relative size-32 text-white/95 sm:size-40" aria-hidden="true" />
                        </div>
                    @endif
                </div>
            </div>

            @if (! empty($stats['students']))
                <div class="glass-strong animate-float absolute -left-1 top-8 flex items-center gap-3 rounded-2xl p-3 pr-5 shadow-lift sm:-left-8">
                    <span class="grid size-11 place-items-center rounded-xl bg-primary/12 text-primary-ink"><x-heroicon-o-users class="size-6" aria-hidden="true" /></span>
                    <div>
                        <p class="text-lg font-extrabold leading-none">{{ site_number($stats['students']) }}+</p>
                        <p class="mt-1 text-xs font-semibold text-muted">{{ __('site.hero.students_card') }}</p>
                    </div>
                </div>
            @endif

            @if ($topper)
                <div class="glass-strong animate-float-slow absolute -right-1 top-1/3 flex items-center gap-3 rounded-2xl p-3 pr-5 shadow-lift sm:-right-8">
                    <span class="grid size-11 place-items-center rounded-xl bg-amber-400/20 text-amber-600 dark:text-amber-300"><x-heroicon-o-trophy class="size-6" aria-hidden="true" /></span>
                    <div>
                        <p class="text-sm font-extrabold leading-none">GPA {{ site_number($topper['gpa'], 2) }}</p>
                        <p class="mt-1 max-w-[9rem] truncate text-xs font-semibold text-muted">{{ $topper['name'] }}</p>
                    </div>
                </div>
            @endif

            @if ($notice)
                <a href="{{ route('notices.show', ['notice' => $notice['id'], 'slug' => $notice['slug'] ?? null]) }}"
                   class="glass-strong animate-float absolute -bottom-5 left-4 right-4 flex items-center gap-3 rounded-2xl p-3.5 shadow-lift transition-transform hover:-translate-y-1 sm:left-auto sm:right-[-1.5rem] sm:w-72">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-accent/15 text-accent-ink"><x-heroicon-o-megaphone class="size-6" aria-hidden="true" /></span>
                    <div class="min-w-0">
                        <p class="text-[0.7rem] font-bold uppercase tracking-wider text-accent-ink">{{ __('site.hero.latest_notice') }}</p>
                        <p class="line-clamp-2 text-sm font-semibold leading-snug">{{ $notice['title'] }}</p>
                    </div>
                </a>
            @endif
        </div>
    </div>

    {{-- Course-name marquee --}}
    @if ($courses->count() > 1)
        <div class="relative border-y border-line/70 bg-surface-solid/40 py-4 backdrop-blur-sm" aria-hidden="true">
            <div class="marquee" style="--marquee-duration: {{ max(25, $courses->count() * 7) }}s">
                <div class="marquee-track">
                    @foreach ([1, 2] as $copy)
                        <ul class="flex shrink-0 items-center gap-10 pr-10" role="presentation">
                            @foreach ($courses as $course)
                                <li class="flex items-center gap-10 whitespace-nowrap text-sm font-bold uppercase tracking-widest text-muted">
                                    {{ $course['name'] }}
                                    <span class="size-1.5 rounded-full bg-primary/50"></span>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</section>
