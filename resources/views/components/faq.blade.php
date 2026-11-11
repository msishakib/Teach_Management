@props(['items'])

<div x-data="accordion(0)" {{ $attributes->class(['mx-auto max-w-3xl space-y-3']) }}>
    @foreach ($items as $i => $faq)
        <div data-reveal style="--d: {{ min($i, 6) * 60 }}ms" class="glass overflow-hidden rounded-2xl transition-shadow hover:shadow-lift">
            <h3>
                <button type="button"
                        id="faq-button-{{ $i }}"
                        @click="toggle({{ $i }})"
                        :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                        aria-controls="faq-panel-{{ $i }}"
                        class="flex w-full items-center justify-between gap-4 p-5 text-left text-base font-semibold sm:p-6 sm:text-lg">
                    <span>{{ $faq->localized('question') }}</span>
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary/10 text-primary-ink transition-transform duration-300 {{ $i === 0 ? 'rotate-45' : '' }}"
                          :class="{ 'rotate-45': open === {{ $i }} }" aria-hidden="true">
                        <x-heroicon-o-plus class="size-4" />
                    </span>
                </button>
            </h3>
            <div id="faq-panel-{{ $i }}" role="region" aria-labelledby="faq-button-{{ $i }}"
                 class="grid transition-[grid-template-rows] duration-300 ease-out {{ $i === 0 ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]' }}"
                 :class="{ 'grid-rows-[1fr]': open === {{ $i }}, 'grid-rows-[0fr]': open !== {{ $i }} }"
                 :inert="open !== {{ $i }}">
                <div class="overflow-hidden">
                    <p class="px-5 pb-5 leading-relaxed text-muted sm:px-6 sm:pb-6">{{ $faq->localized('answer') }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>
