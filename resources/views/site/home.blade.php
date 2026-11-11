@php
    $site = app(\App\Services\SiteProfileService::class)->profile();
    $stats = $home['stats'];
    $firstNotice = $home['notices']->first();
    $topper = $home['toppers']->first();
    $currency = $site['currency'];
    $heroImage = optional($home['banners']->first(fn ($b) => filled($b->image)))->image;

    $whyItems = [
        ['key' => 'faculty', 'icon' => 'academic-cap', 'class' => 'md:col-span-2 md:row-span-2', 'featured' => true],
        ['key' => 'results', 'icon' => 'trophy', 'class' => 'md:col-span-2'],
        ['key' => 'batches', 'icon' => 'user-group', 'class' => ''],
        ['key' => 'online', 'icon' => 'video-camera', 'class' => ''],
        ['key' => 'routine', 'icon' => 'calendar-days', 'class' => 'md:col-span-2'],
        ['key' => 'portal', 'icon' => 'device-phone-mobile', 'class' => 'md:col-span-2'],
    ];

    $orgId = url('/').'#organization';
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'EducationalOrganization',
        '@id' => $orgId,
        'name' => $site['name'],
        'url' => route('home'),
        'description' => $site['description'],
        'logo' => $site['logo'],
        'image' => $heroImage ? public_image($heroImage) : $site['logo'],
        'telephone' => $site['phone'],
        'email' => $site['email'],
        'address' => $site['address'] ? array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $site['address'],
            'addressLocality' => setting('general.city'),
            'addressCountry' => setting('general.country', 'BD'),
        ]) : null,
        'hasMap' => $site['address'] ? 'https://www.google.com/maps?q='.rawurlencode($site['address']) : null,
        'sameAs' => array_values(array_filter($site['socials'], fn ($url) => str_starts_with($url, 'https://') && ! str_contains($url, 'wa.me'))),
    ];
    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $site['name'],
        'url' => route('home'),
        'inLanguage' => app()->getLocale() === 'bn' ? 'bn-BD' : 'en',
        'publisher' => ['@id' => $orgId],
    ];
    $faqSchema = $home['faqs']->isEmpty() ? null : [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $home['faqs']->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq->localized('question'),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->localized('answer')],
        ])->all(),
    ];
@endphp

<x-layouts.site :image="$heroImage ? public_image($heroImage) : null">
    @push('head')
        <script type="application/ld+json">{!! json_encode(array_filter($schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        <script type="application/ld+json">{!! json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @if ($faqSchema)
            <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endif
    @endpush

    {{-- 1. Hero --}}
    <x-hero :banners="$home['banners']" :stats="$stats" :notice="$firstNotice" :topper="$topper" :courses="$home['courses']" />

    {{-- 2. Animated counters --}}
    <x-section id="stats" class="!py-12 sm:!py-16" aria-label="{{ __('site.stats.label') }}">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6">
            <x-stat :value="$stats['students']" suffix="+" :label="__('site.stats.students')" icon="users" :delay="0" />
            <x-stat :value="$stats['teachers']" suffix="+" :label="__('site.stats.teachers')" icon="academic-cap" :delay="80" />
            <x-stat :value="$stats['courses']" suffix="+" :label="__('site.stats.courses')" icon="book-open" :delay="160" />
            @if ($stats['success_rate'] !== null)
                <x-stat :value="$stats['success_rate']" suffix="%" :label="__('site.stats.success')" icon="trophy" :delay="240" />
            @endif
        </div>
    </x-section>

    {{-- 3. Courses --}}
    @if ($home['courses']->isNotEmpty())
        <x-section id="courses" :eyebrow="__('site.courses.eyebrow')" :title="__('site.courses.title')" :subtitle="__('site.courses.subtitle')">
            <div x-data="{ cat: 'all' }">
                @if ($home['categories']->count() > 1)
                    <div class="mb-10 flex flex-wrap justify-center gap-2" role="group" aria-label="{{ __('site.courses.filter') }}">
                        <button type="button" @click="cat = 'all'" :aria-pressed="cat === 'all' ? 'true' : 'false'"
                                class="rounded-full px-5 py-2.5 text-sm font-semibold transition-all bg-primary text-white shadow-lg"
                                :class="{ 'bg-primary text-white shadow-lg': cat === 'all', 'glass text-muted hover:text-ink': cat !== 'all' }">
                            {{ __('site.courses.all') }}
                        </button>
                        @foreach ($home['categories'] as $category)
                            <button type="button" @click="cat = @js($category)" :aria-pressed="cat === @js($category) ? 'true' : 'false'"
                                    class="rounded-full px-5 py-2.5 text-sm font-semibold transition-all glass text-muted hover:text-ink"
                                    :class="{ 'bg-primary text-white shadow-lg': cat === @js($category), 'glass text-muted hover:text-ink': cat !== @js($category) }">
                                {{ $category }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($home['courses'] as $course)
                        <div class="h-full" x-show="cat === 'all' || cat === @js($course['category'])"
                             x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                            <x-course-card :course="$course" :currency="$currency" :delay="($loop->index % 3) * 80" />
                        </div>
                    @endforeach
                </div>
            </div>
        </x-section>
    @endif

    {{-- 4. Why choose us (bento) --}}
    <x-section id="why" :eyebrow="__('site.why.eyebrow')" :title="__('site.why.title')" :subtitle="__('site.why.subtitle')">
        <div class="grid auto-rows-fr gap-4 md:grid-cols-4 lg:gap-5">
            @foreach ($whyItems as $item)
                @if ($item['featured'] ?? false)
                    <div data-reveal class="grad-bg relative flex flex-col justify-between overflow-hidden rounded-3xl p-7 text-white shadow-lift sm:p-9 {{ $item['class'] }}">
                        <div class="absolute -right-16 -top-16 size-64 rounded-full bg-white/15" aria-hidden="true"></div>
                        <div class="absolute -bottom-20 -left-12 size-60 rounded-full bg-white/10" aria-hidden="true"></div>
                        <span class="relative grid size-14 place-items-center rounded-2xl bg-white/20 backdrop-blur">
                            <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-7" aria-hidden="true" />
                        </span>
                        <div class="relative mt-16">
                            <h3 class="text-balance text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl">{{ __('site.why.items.'.$item['key'].'.title') }}</h3>
                            <p class="mt-3 max-w-md text-base leading-relaxed text-white/90">{{ __('site.why.items.'.$item['key'].'.text') }}</p>
                        </div>
                    </div>
                @else
                    <div data-reveal style="--d: {{ $loop->index * 70 }}ms"
                         class="glass group relative overflow-hidden rounded-3xl p-6 transition duration-300 hover:-translate-y-1 hover:shadow-lift sm:p-7 {{ $item['class'] }}">
                        <div class="orb -right-10 -top-10 size-36 opacity-40 transition-opacity group-hover:opacity-90" style="--orb: var(--accent)" aria-hidden="true"></div>
                        <span class="relative grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary-ink">
                            <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-6" aria-hidden="true" />
                        </span>
                        <h3 class="relative mt-5 text-lg font-bold leading-snug tracking-tight">{{ __('site.why.items.'.$item['key'].'.title') }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-muted">{{ __('site.why.items.'.$item['key'].'.text') }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </x-section>

    {{-- 5. Teachers --}}
    @if ($home['teachers']->isNotEmpty())
        <x-section id="teachers" :eyebrow="__('site.teachers.eyebrow')" :title="__('site.teachers.title')" :subtitle="__('site.teachers.subtitle')">
            <div class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                @foreach ($home['teachers'] as $teacher)
                    <x-teacher-card :teacher="$teacher" :delay="($loop->index % 4) * 80" />
                @endforeach
            </div>
        </x-section>
    @endif

    {{-- 6. Results / toppers --}}
    @if ($home['toppers']->isNotEmpty())
        <x-section id="results" :eyebrow="__('site.results.eyebrow')" :title="__('site.results.title')" :subtitle="__('site.results.subtitle')">
            <div x-data="scroller" data-reveal>
                <div class="mb-2 flex justify-end gap-2">
                    <button type="button" @click="scrollBy(-1)" :disabled="!canPrev" class="glass grid size-11 place-items-center rounded-full transition disabled:opacity-40" aria-label="{{ __('site.results.prev') }}">
                        <x-heroicon-o-arrow-left class="size-5" aria-hidden="true" />
                    </button>
                    <button type="button" @click="scrollBy(1)" :disabled="!canNext" class="glass grid size-11 place-items-center rounded-full transition disabled:opacity-40" aria-label="{{ __('site.results.next') }}">
                        <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                    </button>
                </div>

                <div x-ref="track" @scroll.passive="update()" class="snap-row" tabindex="0" role="region" aria-label="{{ __('site.results.title') }}">
                    @foreach ($home['toppers'] as $topperItem)
                        <x-topper-card :topper="$topperItem" />
                    @endforeach
                </div>
            </div>
        </x-section>
    @endif

    {{-- 7. Testimonials --}}
    @if ($home['testimonials']->isNotEmpty())
        @php $testimonialCount = $home['testimonials']->count(); @endphp
        <x-section id="testimonials" :eyebrow="__('site.testimonials.eyebrow')" :title="__('site.testimonials.title')" :subtitle="__('site.testimonials.subtitle')">
            <div x-data="slider({{ $testimonialCount }}, 6500)"
                 @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false"
                 @keydown.arrow-left="prev()" @keydown.arrow-right="next()"
                 @touchstart.passive="swipeStart($event)" @touchend.passive="swipeEnd($event)"
                 data-reveal class="mx-auto max-w-4xl"
                 role="group" aria-roledescription="carousel" aria-label="{{ __('site.testimonials.title') }}">
                <div class="glass-strong relative overflow-hidden rounded-[2rem] p-7 shadow-lift sm:p-12">
                    <x-heroicon-s-chat-bubble-left-right class="absolute right-6 top-6 size-16 text-primary/10 sm:size-24" aria-hidden="true" />

                    <div class="grid" :aria-live="paused ? 'polite' : 'off'">
                        @foreach ($home['testimonials'] as $i => $testimonial)
                            @php
                                $initials = \Illuminate\Support\Str::of($testimonial->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
                            @endphp
                            <figure class="col-start-1 row-start-1 flex flex-col justify-between transition-all duration-700 {{ $i === 0 ? 'visible opacity-100' : 'invisible translate-x-6 opacity-0' }}"
                                    :class="{ 'visible opacity-100 translate-x-0': i === {{ $i }}, 'invisible translate-x-6 opacity-0': i !== {{ $i }} }"
                                    :inert="i !== {{ $i }}" role="group" aria-roledescription="slide"
                                    aria-label="{{ $i + 1 }} / {{ $testimonialCount }}">
                                <div class="mb-5 flex gap-1 text-amber-500" role="img" aria-label="{{ __('site.testimonials.rating', ['n' => $testimonial->rating]) }}">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <x-heroicon-s-star class="size-5 {{ $s <= $testimonial->rating ? '' : 'opacity-25' }}" aria-hidden="true" />
                                    @endfor
                                </div>
                                <blockquote class="text-pretty text-xl font-semibold leading-relaxed tracking-tight sm:text-2xl lg:text-[1.7rem] lg:leading-snug">
                                    “{{ $testimonial->localized('quote') }}”
                                </blockquote>
                                <figcaption class="mt-8 flex items-center gap-4">
                                    @if ($testimonial->photo)
                                        <img src="{{ public_image($testimonial->photo) }}" alt="{{ $testimonial->name }}" width="56" height="56" loading="lazy" decoding="async" class="size-14 rounded-full object-cover ring-2 ring-primary/30">
                                    @else
                                        <span class="grad-bg grid size-14 place-items-center rounded-full text-lg font-extrabold text-white" aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                    <div>
                                        <p class="font-bold">{{ $testimonial->name }}</p>
                                        @if ($testimonial->role)
                                            <p class="text-sm text-muted">{{ $testimonial->role }}</p>
                                        @endif
                                    </div>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>

                @if ($testimonialCount > 1)
                    <div class="mt-6 flex items-center justify-center gap-4">
                        <button type="button" @click="prev()" class="glass grid size-10 place-items-center rounded-full" aria-label="{{ __('site.testimonials.prev') }}">
                            <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
                        </button>
                        <div class="flex items-center gap-2">
                            @foreach ($home['testimonials'] as $i => $t)
                                <button type="button" @click="go({{ $i }})" :aria-current="i === {{ $i }} ? 'true' : null"
                                        aria-label="{{ __('site.testimonials.go_to', ['n' => site_number($i + 1)]) }}"
                                        class="h-2 rounded-full transition-all duration-300 {{ $i === 0 ? 'w-8 bg-primary' : 'w-2 bg-primary/30' }}"
                                        :class="{ 'w-8 bg-primary': i === {{ $i }}, 'w-2 bg-primary/30 hover:bg-primary/60': i !== {{ $i }} }"></button>
                            @endforeach
                        </div>
                        <button type="button" @click="next()" class="glass grid size-10 place-items-center rounded-full" aria-label="{{ __('site.testimonials.next') }}">
                            <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                @endif
            </div>
        </x-section>
    @endif

    {{-- 8. Latest notices --}}
    @if ($home['notices']->isNotEmpty())
        <x-section id="notices" :eyebrow="__('site.notices.eyebrow')" :title="__('site.notices.title')" :subtitle="__('site.notices.subtitle')">
            {{-- Ticker --}}
            <div data-reveal class="glass mb-8 flex items-center overflow-hidden rounded-full p-1.5">
                <span class="grad-bg relative z-10 flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-xs font-extrabold uppercase tracking-wider text-white">
                    <x-heroicon-o-megaphone class="size-4" aria-hidden="true" /> {{ __('site.notices.latest') }}
                </span>
                <div class="marquee min-w-0 flex-1" style="--marquee-duration: {{ max(30, $home['notices']->count() * 9) }}s">
                    <div class="marquee-track">
                        @foreach ([1, 2] as $copy)
                            <ul class="flex shrink-0 items-center" role="list" @if ($copy === 2) aria-hidden="true" @endif>
                                @foreach ($home['notices'] as $notice)
                                    <li class="flex items-center whitespace-nowrap px-6 text-sm font-semibold">
                                        <a href="{{ route('notices.show', ['notice' => $notice['id'], 'slug' => $notice['slug'] ?? null]) }}" @if ($copy === 2) tabindex="-1" @endif class="hover:text-primary-ink">{{ $notice['title'] }}</a>
                                        <span class="ml-12 size-1.5 rounded-full bg-accent/60" aria-hidden="true"></span>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- List --}}
            <ul class="mx-auto grid max-w-4xl gap-3" role="list">
                @foreach ($home['notices']->take(5) as $notice)
                    <li data-reveal style="--d: {{ $loop->index * 60 }}ms">
                        <a href="{{ route('notices.show', ['notice' => $notice['id'], 'slug' => $notice['slug'] ?? null]) }}"
                           class="glass group flex items-center gap-4 rounded-2xl p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-lift sm:gap-5 sm:p-5">
                            <time datetime="{{ $notice['published_at']->toDateString() }}" class="grid size-16 shrink-0 place-items-center rounded-2xl bg-primary/10 text-center leading-tight text-primary-ink">
                                <span>
                                    <span class="block text-xl font-extrabold">{{ site_date($notice['published_at'], 'd') }}</span>
                                    <span class="block text-[0.7rem] font-bold uppercase tracking-wider">{{ site_date($notice['published_at'], 'MMM') }}</span>
                                </span>
                            </time>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate font-bold leading-snug">{{ $notice['title'] }}</h3>
                                <p class="mt-1 line-clamp-1 text-sm text-muted sm:line-clamp-2">{{ $notice['excerpt'] }}</p>
                            </div>
                            <x-heroicon-o-arrow-up-right class="size-5 shrink-0 text-muted transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary-ink" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>

            <p class="mt-8 text-center">
                <a href="{{ route('notices.index') }}" class="btn btn-ghost">{{ __('site.notices.view_all') }} <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
            </p>
        </x-section>
    @endif

    {{-- 9. Admission CTA --}}
    <section class="relative py-8 sm:py-12" aria-labelledby="cta-title">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div data-reveal class="grad-bg relative isolate overflow-hidden rounded-[2rem] px-6 py-14 text-center text-white shadow-lift sm:rounded-[2.5rem] sm:px-12 sm:py-20">
                <div class="absolute -left-20 -top-24 -z-10 size-80 rounded-full bg-white/15" aria-hidden="true"></div>
                <div class="absolute -bottom-28 -right-16 -z-10 size-96 rounded-full bg-white/10" aria-hidden="true"></div>
                <div class="absolute left-1/2 top-0 -z-10 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-white/60 to-transparent" aria-hidden="true"></div>

                <p class="mx-auto mb-5 inline-flex items-center gap-2 rounded-full bg-white/20 px-4 py-1.5 text-xs font-bold uppercase tracking-wider backdrop-blur">
                    <x-heroicon-o-sparkles class="size-4" aria-hidden="true" /> {{ __('site.cta.badge') }}
                </p>
                <h2 id="cta-title" class="mx-auto max-w-3xl text-balance text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">{{ __('site.cta.title') }}</h2>
                <p class="mx-auto mt-5 max-w-2xl text-pretty text-lg text-white/90">{{ __('site.cta.text') }}</p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('apply') }}" class="btn btn-light !px-8 !py-3.5 text-base">{{ __('site.cta.button') }} <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" /></a>
                    @if ($site['phone'])
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $site['phone']) }}" class="btn !border !border-white/40 !px-8 !py-3.5 text-base text-white hover:bg-white/15">
                            <x-heroicon-o-phone class="size-5" aria-hidden="true" /> {{ $site['phone'] }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- 10. FAQ --}}
    @if ($home['faqs']->isNotEmpty())
        <x-section id="faq" :eyebrow="__('site.faq.eyebrow')" :title="__('site.faq.title')" :subtitle="__('site.faq.subtitle')">
            <x-faq :items="$home['faqs']" />
        </x-section>
    @endif

    {{-- 11. Contact --}}
    <x-section id="contact" :eyebrow="__('site.contact.eyebrow')" :title="__('site.contact.title')" :subtitle="__('site.contact.subtitle')">
        <div class="grid gap-6 lg:grid-cols-[1fr_1.05fr] lg:gap-8">
            <div class="space-y-6" data-reveal>
                <div class="glass space-y-5 rounded-3xl p-6 sm:p-8">
                    @foreach ([
                        ['map-pin', __('site.contact.address'), $site['address'], null],
                        ['phone', __('site.contact.phone'), $site['phone'], $site['phone'] ? 'tel:'.preg_replace('/[^0-9+]/', '', $site['phone']) : null],
                        ['envelope', __('site.contact.email'), $site['email'], $site['email'] ? 'mailto:'.$site['email'] : null],
                        ['clock', __('site.contact.hours'), $site['office_hours'], null],
                    ] as [$icon, $label, $value, $href])
                        @if ($value)
                            <div class="flex items-start gap-4">
                                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary-ink">
                                    <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wider text-muted">{{ $label }}</p>
                                    @if ($href)
                                        <a href="{{ $href }}" class="break-words font-semibold hover:text-primary-ink">{{ $value }}</a>
                                    @else
                                        <p class="font-semibold">{{ $value }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($site['map_embed_url'])
                    <div class="glass overflow-hidden rounded-3xl p-2">
                        <iframe src="{{ $site['map_embed_url'] }}" title="{{ __('site.contact.map') }}" width="600" height="320"
                                loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen
                                class="h-72 w-full rounded-[1.25rem] border-0 sm:h-80"></iframe>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('contact.store') }}" data-reveal style="--d: 100ms"
                  class="glass-strong space-y-5 rounded-3xl p-6 shadow-soft sm:p-8" novalidate>
                @csrf

                @if (session('status'))
                    <div role="status" class="flex items-start gap-3 rounded-2xl bg-emerald-500/12 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="size-5 shrink-0" aria-hidden="true" /> {{ session('status') }}
                    </div>
                @endif

                {{-- Honeypot --}}
                <div class="sr-only" aria-hidden="true">
                    <label>Website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="contact-name" class="mb-1.5 block text-sm font-semibold">{{ __('site.contact.name') }}</label>
                        <input id="contact-name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="field" @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror>
                        @error('name') <p id="contact-name-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="contact-email" class="mb-1.5 block text-sm font-semibold">{{ __('site.contact.email_field') }}</label>
                        <input id="contact-email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="field" @error('email') aria-invalid="true" aria-describedby="contact-email-error" @enderror>
                        @error('email') <p id="contact-email-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="contact-phone" class="mb-1.5 block text-sm font-semibold">{{ __('site.contact.phone_field') }}</label>
                        <input id="contact-phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" class="field" @error('phone') aria-invalid="true" aria-describedby="contact-phone-error" @enderror>
                        @error('phone') <p id="contact-phone-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="contact-subject" class="mb-1.5 block text-sm font-semibold">{{ __('site.contact.subject') }}</label>
                        <input id="contact-subject" name="subject" type="text" value="{{ old('subject') }}" class="field">
                    </div>
                </div>

                <div>
                    <label for="contact-message" class="mb-1.5 block text-sm font-semibold">{{ __('site.contact.message') }}</label>
                    <textarea id="contact-message" name="message" rows="5" required class="field resize-y" @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror>{{ old('message') }}</textarea>
                    @error('message') <p id="contact-message-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-full !py-3.5 text-base">
                    {{ __('site.contact.send') }} <x-heroicon-o-paper-airplane class="size-5" aria-hidden="true" />
                </button>
            </form>
        </div>
    </x-section>
</x-layouts.site>
