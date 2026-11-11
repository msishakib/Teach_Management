@props(['teacher', 'delay' => 0])

@php
    $initials = \Illuminate\Support\Str::of($teacher['name'])->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp

<article data-reveal style="--d: {{ (int) $delay }}ms"
         {{ $attributes->class(['group glass overflow-hidden rounded-3xl transition duration-300 hover:-translate-y-1.5 hover:shadow-lift']) }}>
    <div class="relative aspect-[4/5] overflow-hidden bg-primary/10">
        @if ($teacher['photo'])
            <img src="{{ public_image($teacher['photo']) }}" alt="{{ $teacher['name'] }}" width="480" height="600"
                 loading="lazy" decoding="async"
                 class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="grad-bg grid size-full place-items-center text-5xl font-extrabold text-white/95" aria-hidden="true">{{ $initials }}</div>
        @endif

        @if ($teacher['bio'])
            <div class="absolute inset-x-0 bottom-0 translate-y-full bg-gradient-to-t from-black/85 to-black/40 p-4 text-sm leading-relaxed text-white transition-transform duration-300 group-hover:translate-y-0 group-focus-within:translate-y-0 max-sm:hidden">
                <p class="line-clamp-4">{{ $teacher['bio'] }}</p>
            </div>
        @endif
    </div>

    <div class="p-5">
        <h3 class="text-lg font-bold leading-snug tracking-tight">{{ $teacher['name'] }}</h3>
        @if ($teacher['subject'])
            <p class="mt-2 inline-flex rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary-ink">{{ $teacher['subject'] }}</p>
        @endif
        @if ($teacher['qualification'])
            <p class="mt-2 text-sm text-muted">{{ $teacher['qualification'] }}</p>
        @endif
    </div>
</article>
