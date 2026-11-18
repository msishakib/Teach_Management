@php
    $site = app(\App\Services\SiteProfileService::class)->profile();
    $currency = $site['currency'];
    $price = $course->effectivePrice();
    $discount = $course->hasDiscount();
    $image = $thumbnail?->publicUrl() ?? public_image($course->profile?->image);
    $durationLabel = course_duration((int) $stats['duration']);
    $learning = collect($course->what_you_learn ?? [])->filter()->values();
    $totalLessons = (int) $stats['lessons'];
    $hasFreePreview = collect($curriculum)
        ->flatMap(fn (array $section): array => $section['lessons'])
        ->contains(fn (array $lesson): bool => (bool) ($lesson['is_free_preview'] ?? false));
    $loginUrl = route('filament.student.auth.login');

    $courseSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => $course->name,
        'description' => strip_tags($course->short_description ?: (string) $course->description),
        'provider' => [
            '@type' => 'EducationalOrganization',
            'name' => $site['name'],
            'url' => route('home'),
        ],
    ];

    if ($image) {
        $courseSchema['image'] = $image;
    }
@endphp

<x-layouts.site :title="$course->name" :description="$course->short_description ?: $course->description" :image="$image" type="product">
    @push('head')
        <script type="application/ld+json">{!! json_encode($courseSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush

    {{-- Hero --}}
    <section class="relative overflow-hidden pb-10 pt-10 sm:pb-16 sm:pt-16" aria-labelledby="course-title">
        <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav class="mb-6 text-sm font-semibold text-muted" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-2" role="list">
                    <li><a href="{{ route('home') }}" class="hover:text-primary-ink">{{ __('course.breadcrumb.home') }}</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('home') }}#courses" class="hover:text-primary-ink">{{ __('course.breadcrumb.courses') }}</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="text-ink" aria-current="page">{{ $course->name }}</li>
                </ol>
            </nav>

            <div class="grid items-start gap-8 lg:grid-cols-[1.35fr_0.9fr] lg:gap-12">
                <div data-reveal>
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        @if ($course->hasOnlineComponent())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary-ink">
                                <x-heroicon-o-globe-alt class="size-3.5" aria-hidden="true" />
                                {{ $course->delivery_mode === \App\Enums\DeliveryMode::Hybrid ? __('course.hero.hybrid') : __('course.hero.online') }}
                            </span>
                        @endif
                        @if ($hasFreePreview)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                                <x-heroicon-o-check-circle class="size-3.5" aria-hidden="true" />
                                {{ __('course.curriculum.free') }}
                            </span>
                        @endif
                    </div>

                    <h1 id="course-title" class="text-balance text-3xl font-extrabold leading-[1.08] tracking-tight sm:text-4xl lg:text-5xl">{{ $course->name }}</h1>

                    @if ($course->short_description || $course->description)
                        <p class="mt-5 max-w-2xl text-pretty text-base leading-relaxed text-muted sm:text-lg">{{ $course->short_description ?: $course->description }}</p>
                    @endif

                    <dl class="mt-8 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach (array_filter([
                            ['icon' => 'play-circle', 'label' => __('course.meta.lessons'), 'value' => site_number($totalLessons)],
                            ['icon' => 'clock', 'label' => __('course.meta.duration'), 'value' => $durationLabel],
                            ['icon' => 'signal', 'label' => __('course.meta.level'), 'value' => $course->level],
                            ['icon' => 'language', 'label' => __('course.meta.language'), 'value' => $course->language],
                        ], fn ($item) => filled($item['value'])) as $item)
                            <div class="glass rounded-2xl p-4">
                                <dt class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted">
                                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-4 text-primary-ink" aria-hidden="true" />
                                    {{ $item['label'] }}
                                </dt>
                                <dd class="mt-1.5 text-lg font-extrabold tracking-tight">{{ $item['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Price / enrolment box --}}
                <aside data-reveal style="--d: 100ms" class="lg:sticky lg:top-24">
                    <div class="glass-strong overflow-hidden rounded-3xl shadow-lift">
                        @if ($image)
                            <img src="{{ $image }}" alt="{{ $course->name }}" width="640" height="400" fetchpriority="high" class="aspect-[8/5] w-full object-cover">
                        @else
                            <div class="grad-bg grid aspect-[8/5] place-items-center">
                                <x-heroicon-o-book-open class="size-14 text-white/90" aria-hidden="true" />
                            </div>
                        @endif

                        <div class="space-y-5 p-6 sm:p-7">
                            <div>
                                @if ($price !== null)
                                    <div class="flex flex-wrap items-end gap-2">
                                        <p class="text-3xl font-extrabold tracking-tight">{{ $currency }}{{ site_number($price, 2, false) }}</p>
                                        @if ($discount)
                                            <p class="pb-1 text-base font-semibold text-muted line-through">{{ $currency }}{{ site_number((float) $course->price, 2, false) }}</p>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-xl font-extrabold">{{ __('course.price.free') }}</p>
                                @endif
                                <p class="mt-1 text-sm font-semibold text-muted">
                                    @if ($course->access_days !== null)
                                        {{ __('course.price.days', ['days' => site_number($course->access_days)]) }}
                                    @else
                                        {{ __('course.price.lifetime') }}
                                    @endif
                                </p>
                            </div>

                            @if ($isEnrolled)
                                <a href="{{ route('courses.learn', ['course' => $course->code]) }}" class="btn btn-primary w-full !py-3.5 text-base">
                                    {{ __('course.price.continue') }}
                                    <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                                </a>
                            @elseif (! $course->hasOnlineComponent())
                                <a href="{{ route('apply', ['course' => $course->id]) }}" class="btn btn-primary w-full !py-3.5 text-base">
                                    {{ __('course.price.enroll') }}
                                    <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                                </a>
                            @elseif (auth()->check())
                                @if ($price !== null && (float) $price > 0)
                                    <a href="{{ route('courses.checkout', ['course' => $course->code]) }}" class="btn btn-primary w-full !py-3.5 text-base">
                                        {{ __('course.price.buy') }}
                                        <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('courses.enroll', ['course' => $course->code]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary w-full !py-3.5 text-base">
                                            {{ __('course.price.enroll') }}
                                            <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                                        </button>
                                    </form>
                                @endif
                            @else
                                <a href="{{ $loginUrl }}" class="btn btn-primary w-full !py-3.5 text-base">
                                    {{ __('course.price.login') }}
                                    <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                                </a>
                            @endif

                            <ul class="space-y-2.5 text-sm font-medium text-muted" role="list">
                                <li class="flex items-center gap-2">
                                    <x-heroicon-o-check class="size-4 text-emerald-600" aria-hidden="true" />
                                    {{ $course->access_days !== null ? __('course.price.days', ['days' => site_number($course->access_days)]) : __('course.price.lifetime') }}
                                </li>
                                <li class="flex items-center gap-2"><x-heroicon-o-check class="size-4 text-emerald-600" aria-hidden="true" /> {{ __('course.price.support') }}</li>
                                <li class="flex items-center gap-2"><x-heroicon-o-check class="size-4 text-emerald-600" aria-hidden="true" /> {{ __('course.price.certificate') }}</li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    {{-- What you learn + requirements --}}
    <x-section id="overview" :eyebrow="__('course.hero.overview')" :title="__('course.what_you_learn.title')">
        <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
            <div data-reveal class="glass rounded-3xl p-6 sm:p-8">
                @if ($learning->isNotEmpty())
                    <ul class="grid gap-3 sm:grid-cols-2" role="list">
                        @foreach ($learning as $item)
                            <li class="flex items-start gap-3 text-sm font-medium leading-relaxed">
                                <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                                    <x-heroicon-o-check class="size-4" aria-hidden="true" />
                                </span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-muted">{{ __('course.what_you_learn.empty') }}</p>
                @endif
            </div>

            <div data-reveal class="glass rounded-3xl p-6 sm:p-8">
                <h3 class="text-lg font-extrabold tracking-tight">{{ __('course.requirements.title') }}</h3>
                @if (filled($course->requirements))
                    <div class="prose prose-sm mt-3 max-w-none text-muted">{!! nl2br(e($course->requirements)) !!}</div>
                @else
                    <p class="mt-3 text-sm leading-relaxed text-muted">{{ __('course.requirements.empty') }}</p>
                @endif
            </div>
        </div>
    </x-section>

    {{-- Curriculum --}}
    <x-section id="curriculum" :eyebrow="__('course.curriculum.eyebrow')" :title="__('course.curriculum.title')" :subtitle="__('course.curriculum.lessons', ['count' => site_number($totalLessons)]).' · '.$durationLabel">
        <div
            x-data="{
                open: 0,
                previewOpen: false,
                loading: false,
                previewType: null,
                previewUrl: null,
                previewError: null,
                async preview(url) {
                    this.previewOpen = true;
                    this.loading = true;
                    this.previewError = null;
                    this.previewType = null;
                    this.previewUrl = null;
                    try {
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const body = await response.json();
                        if (!response.ok) { throw new Error(body.message || 'Unable to load this preview.'); }
                        this.previewType = body.video_type;
                        this.previewUrl = body.url;
                    } catch (error) {
                        this.previewError = error.message;
                    } finally {
                        this.loading = false;
                    }
                },
                closePreview() {
                    this.previewOpen = false;
                    this.previewType = null;
                    this.previewUrl = null;
                    this.previewError = null;
                }
            }"
            @keydown.escape.window="closePreview()"
            data-reveal
        >
            @if (count($curriculum) === 0)
                <p class="glass rounded-3xl p-6 text-center text-sm text-muted">{{ __('course.curriculum.empty') }}</p>
            @else
                <div class="mx-auto max-w-4xl space-y-3">
                    @foreach ($curriculum as $section)
                        <div class="glass overflow-hidden rounded-3xl">
                            <button type="button"
                                    @click="open = open === {{ $loop->index }} ? -1 : {{ $loop->index }}"
                                    :aria-expanded="open === {{ $loop->index }} ? 'true' : 'false'"
                                    class="flex w-full items-center justify-between gap-4 p-5 text-left sm:p-6">
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-muted">{{ __('course.curriculum.section') }} {{ site_number($loop->iteration) }}</span>
                                    <span class="mt-1 block truncate text-lg font-extrabold tracking-tight">{{ $section['title'] }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-3">
                                    <span class="hidden text-xs font-semibold text-muted sm:inline">{{ __('course.curriculum.lessons', ['count' => site_number(count($section['lessons']))]) }}</span>
                                    <x-heroicon-o-chevron-down class="size-5 transition-transform" ::class="open === {{ $loop->index }} ? 'rotate-180' : ''" aria-hidden="true" />
                                </span>
                            </button>

                            <div x-show="open === {{ $loop->index }}" x-collapse>
                                <ul class="divide-y divide-black/5 border-t border-black/5 dark:divide-white/10 dark:border-white/10" role="list">
                                    @forelse ($section['lessons'] as $lesson)
                                        <li class="flex flex-wrap items-center gap-3 px-5 py-3.5 sm:px-6">
                                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary-ink">
                                                <x-dynamic-component :component="\App\Enums\LessonType::tryFrom($lesson['type'])?->icon() ?? 'heroicon-o-play-circle'" class="size-4" aria-hidden="true" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold">{{ $lesson['title'] }}</p>
                                                <p class="mt-0.5 flex items-center gap-2 text-xs text-muted">
                                                    <span>{{ __('course.types.'.$lesson['type']) }}</span>
                                                    @if ($lesson['duration_seconds'] > 0)
                                                        <span aria-hidden="true">·</span>
                                                        <span>{{ course_duration($lesson['duration_seconds']) }}</span>
                                                    @endif
                                                    @if ($lesson['drip_after_days'] !== null)
                                                        <span aria-hidden="true">·</span>
                                                        <span>{{ __('course.player.locked', ['days' => site_number($lesson['drip_after_days'])]) }}</span>
                                                    @endif
                                                </p>
                                            </div>
                                            @if ($lesson['is_free_preview'])
                                                <button type="button"
                                                        @click="preview(@js(route('lessons.video', ['lesson' => $lesson['id']])))"
                                                        class="btn btn-ghost !px-3 !py-1.5 text-xs">
                                                    <x-heroicon-o-play class="size-3.5" aria-hidden="true" />
                                                    {{ __('course.curriculum.preview') }}
                                                </button>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/12 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                                                    {{ __('course.curriculum.free') }}
                                                </span>
                                            @else
                                                <x-heroicon-o-lock-closed class="size-4 text-muted" aria-label="{{ __('course.curriculum.locked') }}" />
                                            @endif
                                        </li>
                                    @empty
                                        <li class="px-5 py-4 text-sm text-muted sm:px-6">{{ __('course.curriculum.empty') }}</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Preview modal --}}
            <div x-show="previewOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="{{ __('course.curriculum.preview') }}">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closePreview()" aria-hidden="true"></div>
                <div class="relative w-full max-w-3xl overflow-hidden rounded-3xl bg-black shadow-lift">
                    <button type="button" @click="closePreview()" class="absolute right-3 top-3 z-10 grid size-10 place-items-center rounded-full bg-black/60 text-white backdrop-blur" aria-label="{{ __('site.gallery.close') }}">
                        <x-heroicon-o-x-mark class="size-5" aria-hidden="true" />
                    </button>
                    <div class="aspect-video w-full">
                        <template x-if="loading">
                            <div class="grid size-full place-items-center text-sm font-semibold text-white/80">Loading…</div>
                        </template>
                        <template x-if="! loading && previewError">
                            <div class="grid size-full place-items-center p-6 text-center text-sm font-semibold text-white/80" x-text="previewError"></div>
                        </template>
                        <template x-if="! loading && ! previewError && previewType === 'embed'">
                            <iframe :src="previewUrl" class="size-full" title="{{ __('course.curriculum.preview') }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                        </template>
                        <template x-if="! loading && ! previewError && previewType === 'upload'">
                            <video :src="previewUrl" class="size-full" controls autoplay playsinline></video>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </x-section>

    {{-- Instructor --}}
    @if ($instructor)
        <x-section id="instructor" :eyebrow="__('course.instructor.title')" :title="$instructor->teacher->name">
            <div class="glass mx-auto flex max-w-3xl flex-col items-center gap-6 rounded-3xl p-6 text-center sm:flex-row sm:p-8 sm:text-left" data-reveal>
                @php $photo = $instructor->teacher->mediaFor(\App\Enums\MediaCategory::TeacherPhoto) ?? public_image($instructor->photo); @endphp
                @if ($photo)
                    @if ($photo instanceof \App\Models\Media)
                        <x-media-image :media="$photo" :alt="$instructor->teacher->name" sizes="112px" class="size-28 shrink-0 rounded-2xl object-cover" />
                    @else
                        <img src="{{ $photo }}" alt="{{ $instructor->teacher->name }}" width="112" height="112" loading="lazy" class="size-28 shrink-0 rounded-2xl object-cover">
                    @endif
                @else
                    <span class="grad-bg grid size-28 shrink-0 place-items-center rounded-2xl text-2xl font-extrabold text-white" aria-hidden="true">{{ \Illuminate\Support\Str::of($instructor->teacher->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider text-primary-ink">{{ $instructor->subject ?: __('course.instructor.fallback') }}</p>
                    <h3 class="mt-1 text-xl font-extrabold tracking-tight">{{ $instructor->teacher->name }}</h3>
                    @if ($instructor->qualification)
                        <p class="mt-1 text-sm font-semibold text-muted">{{ $instructor->qualification }}</p>
                    @endif
                    <p class="mt-3 text-sm leading-relaxed text-muted">{{ $instructor->bio ?: __('course.instructor.bio') }}</p>
                </div>
            </div>
        </x-section>
    @endif
</x-layouts.site>
