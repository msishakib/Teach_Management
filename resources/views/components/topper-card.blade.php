@props(['topper'])

@php
    $initials = \Illuminate\Support\Str::of($topper['name'])->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    // Contrast-safe medal colours (dark text on gold/silver/bronze).
    $medal = match ($topper['rank']) {
        1 => 'bg-amber-300 text-amber-950',
        2 => 'bg-slate-300 text-slate-900',
        default => 'bg-orange-300 text-orange-950',
    };
@endphp

<article {{ $attributes->class(['glass group relative w-[17.5rem] overflow-hidden rounded-3xl p-6 transition duration-300 hover:-translate-y-1.5 hover:shadow-lift sm:w-80']) }}>
    <div class="orb -right-12 -top-12 size-44 opacity-50 transition-opacity group-hover:opacity-90" style="--orb: var(--primary)" aria-hidden="true"></div>

    <div class="relative flex items-start justify-between gap-3">
        @if ($topper['photo'])
            <img src="{{ public_image($topper['photo']) }}" alt="{{ $topper['name'] }}" width="72" height="72"
                 loading="lazy" decoding="async" class="size-[4.5rem] rounded-2xl object-cover ring-2 ring-white/60">
        @else
            <span class="grad-bg grid size-[4.5rem] place-items-center rounded-2xl text-xl font-extrabold text-white" aria-hidden="true">{{ $initials }}</span>
        @endif

        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-extrabold {{ $medal }}">
            <x-heroicon-s-trophy class="size-4" aria-hidden="true" />
            {{ __('site.results.rank', ['n' => site_number($topper['rank'])]) }}
        </span>
    </div>

    <h3 class="relative mt-5 text-lg font-bold leading-snug">{{ $topper['name'] }}</h3>
    <p class="relative mt-1 line-clamp-2 min-h-[2.5rem] text-sm text-muted">
        {{ $topper['exam'] }}@if ($topper['course']) · {{ $topper['course'] }}@endif
    </p>

    <dl class="relative mt-5 grid grid-cols-2 gap-3 border-t border-line pt-4">
        <div>
            <dt class="text-xs font-semibold text-muted">{{ __('site.results.gpa') }}</dt>
            <dd class="text-2xl font-extrabold tracking-tight text-gradient">{{ site_number($topper['gpa'], 2) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-muted">{{ __('site.results.score') }}</dt>
            <dd class="text-2xl font-extrabold tracking-tight">{{ site_number($topper['percentage'], 1) }}%</dd>
        </div>
    </dl>
</article>
