@props([
    'value' => 0,
    'label',
    'suffix' => '',
    'icon' => 'sparkles',
    'delay' => 0,
])

<div data-reveal style="--d: {{ (int) $delay }}ms"
     {{ $attributes->class(['glass group relative overflow-hidden rounded-3xl p-6 transition duration-300 hover:-translate-y-1 hover:shadow-lift sm:p-8']) }}>
    <div class="orb -right-10 -top-10 size-40 opacity-60 transition-opacity group-hover:opacity-100" style="--orb: var(--accent)" aria-hidden="true"></div>

    <span class="relative grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary-ink">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" />
    </span>

    <p class="relative mt-5 flex items-baseline gap-0.5 text-4xl font-extrabold tracking-tight sm:text-5xl">
        <span x-data="counter({{ (int) $value }})" x-text="display" class="tabular-nums">{{ site_number($value) }}</span>
        @if ($suffix !== '')
            <span class="text-gradient">{{ $suffix }}</span>
        @endif
    </p>
    <p class="relative mt-1 font-semibold text-muted">{{ $label }}</p>
</div>
