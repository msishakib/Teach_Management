<x-layouts.site :title="__('site.gallery.title')" :description="__('site.gallery.subtitle')">
    <x-section id="gallery" :eyebrow="__('site.gallery.eyebrow')" :title="__('site.gallery.title')" :subtitle="__('site.gallery.subtitle')">
        @if ($images->isEmpty())
            <p class="glass mx-auto max-w-xl rounded-3xl p-10 text-center text-muted">{{ __('site.gallery.empty') }}</p>
        @else
            @php
                $items = $images->map(fn ($image) => [
                    'src' => public_image($image->image),
                    'title' => $image->localized('title'),
                ])->values();
            @endphp

            <div x-data="lightbox(@js($items))"
                 @keydown.escape.window="current !== null && hide()"
                 @keydown.arrow-right.window="current !== null && next()"
                 @keydown.arrow-left.window="current !== null && prev()">
                <ul class="columns-2 gap-3 sm:columns-3 sm:gap-4 lg:columns-4" role="list">
                    @foreach ($items as $i => $item)
                        <li class="mb-3 break-inside-avoid sm:mb-4" data-reveal style="--d: {{ min($i % 4, 3) * 60 }}ms">
                            <button type="button" @click="show({{ $i }})" class="glass group block w-full overflow-hidden rounded-2xl p-1.5 transition duration-300 hover:-translate-y-1 hover:shadow-lift"
                                    aria-label="{{ __('site.gallery.open', ['title' => $item['title']]) }}">
                                <img src="{{ $item['src'] }}" alt="{{ $item['title'] }}" width="640" height="{{ $i % 3 === 0 ? 800 : 480 }}"
                                     loading="lazy" decoding="async"
                                     class="w-full rounded-xl object-cover transition duration-500 group-hover:scale-[1.03] {{ $i % 3 === 0 ? 'aspect-[4/5]' : 'aspect-[4/3]' }}">
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div x-show="current !== null" x-cloak x-transition.opacity
                     class="fixed inset-0 z-[70] grid place-items-center bg-black/85 p-4 backdrop-blur-sm"
                     role="dialog" aria-modal="true" aria-label="{{ __('site.gallery.title') }}" @click.self="hide()">
                    <button type="button" x-ref="close" @click="hide()" class="absolute right-4 top-4 grid size-11 place-items-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="{{ __('site.gallery.close') }}">
                        <x-heroicon-o-x-mark class="size-6" aria-hidden="true" />
                    </button>
                    <button type="button" @click="prev()" class="absolute left-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="{{ __('site.gallery.prev') }}">
                        <x-heroicon-o-chevron-left class="size-6" aria-hidden="true" />
                    </button>
                    <button type="button" @click="next()" class="absolute right-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="{{ __('site.gallery.next') }}">
                        <x-heroicon-o-chevron-right class="size-6" aria-hidden="true" />
                    </button>

                    <figure class="max-h-full max-w-5xl">
                        <img :src="current !== null ? items[current].src : ''" :alt="current !== null ? items[current].title : ''"
                             class="max-h-[80dvh] w-auto max-w-full rounded-2xl object-contain shadow-2xl">
                        <figcaption class="mt-3 text-center text-sm font-semibold text-white/90" x-text="current !== null ? items[current].title : ''"></figcaption>
                    </figure>
                </div>
            </div>
        @endif
    </x-section>
</x-layouts.site>
