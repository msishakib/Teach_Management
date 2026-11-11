@props([
    'course',
    'currency' => '৳',
    'delay' => 0,
])

@php
    $period = match ($course['price_period']) {
        'monthly' => __('site.courses.per_month'),
        'yearly' => __('site.courses.per_year'),
        'one_time' => __('site.courses.one_time'),
        default => null,
    };
@endphp

<article data-reveal style="--d: {{ (int) $delay }}ms"
         {{ $attributes->class(['group glass flex h-full flex-col overflow-hidden rounded-3xl transition duration-300 hover:-translate-y-1.5 hover:shadow-lift focus-within:-translate-y-1.5 focus-within:shadow-lift']) }}>
    <div class="relative aspect-[8/5] overflow-hidden bg-primary/10">
        @if ($course['image'])
            <img src="{{ public_image($course['image']) }}" alt="{{ $course['name'] }}" width="640" height="400"
                 loading="lazy" decoding="async"
                 class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="grad-bg grid size-full place-items-center">
                <x-heroicon-o-book-open class="size-14 text-white/90" aria-hidden="true" />
            </div>
        @endif

        <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
            @if ($course['category'])
                <span class="rounded-full bg-black/55 px-3 py-1 text-xs font-bold text-white backdrop-blur-md">{{ $course['category'] }}</span>
            @else
                <span></span>
            @endif
            @if ($course['featured'])
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-300 px-2.5 py-1 text-xs font-bold text-amber-950">
                    <x-heroicon-s-star class="size-3.5" aria-hidden="true" /> {{ __('site.courses.popular') }}
                </span>
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col p-6">
        <h3 class="text-xl font-bold leading-snug tracking-tight">{{ $course['name'] }}</h3>

        @if ($course['tagline'] || $course['description'])
            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-muted">{{ $course['tagline'] ?: $course['description'] }}</p>
        @endif

        @if ($course['duration'])
            <p class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-muted">
                <x-heroicon-o-clock class="size-4 text-primary-ink" aria-hidden="true" />
                <span class="sr-only">{{ __('site.courses.duration') }}:</span>{{ $course['duration'] }}
            </p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-3 pt-6">
            <div>
                @if ($course['price'] !== null)
                    <p class="text-2xl font-extrabold tracking-tight">{{ $currency }}{{ site_number($course['price']) }}</p>
                    @if ($period)
                        <p class="text-xs font-medium text-muted">{{ $period }}</p>
                    @endif
                @else
                    <p class="text-sm font-semibold text-muted">{{ __('site.courses.contact_price') }}</p>
                @endif
            </div>

            <a href="{{ route('apply', ['course' => $course['id']]) }}" class="btn btn-primary !px-5 !py-2.5"
               aria-label="{{ __('site.courses.enroll_in', ['course' => $course['name']]) }}">
                {{ __('site.courses.enroll') }}
                <x-heroicon-o-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </a>
        </div>
    </div>
</article>
