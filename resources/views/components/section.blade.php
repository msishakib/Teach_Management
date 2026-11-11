@props([
    'id' => null,
    'eyebrow' => null,
    'title' => null,
    'subtitle' => null,
])

<section @if ($id) id="{{ $id }}" @endif @if ($title && $id) aria-labelledby="{{ $id }}-title" @endif {{ $attributes->class(['relative py-16 sm:py-24']) }}>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($title)
            <header data-reveal class="mx-auto mb-10 max-w-3xl text-center sm:mb-14">
                @if ($eyebrow)
                    <p class="eyebrow mb-4">{{ $eyebrow }}</p>
                @endif
                <h2 @if ($id) id="{{ $id }}-title" @endif class="text-balance text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-4xl lg:text-5xl">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mx-auto mt-4 max-w-2xl text-pretty text-base text-muted sm:text-lg">{{ $subtitle }}</p>
                @endif
            </header>
        @endif

        {{ $slot }}
    </div>
</section>
