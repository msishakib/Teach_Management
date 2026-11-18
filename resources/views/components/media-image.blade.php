@props([
    'media',
    'alt' => null,
    'sizes' => '100vw',
    'loading' => 'lazy',
    'fetchpriority' => null,
])

@php
    $image = $media->responsiveImageData();
@endphp

@if ($image)
    <img
        src="{{ $image['src'] }}"
        @if ($image['srcset'] !== '') srcset="{{ $image['srcset'] }}" sizes="{{ $sizes }}" @endif
        @if ($image['width']) width="{{ $image['width'] }}" @endif
        @if ($image['height']) height="{{ $image['height'] }}" @endif
        alt="{{ $alt ?? $media->displayName() }}"
        loading="{{ $loading }}"
        @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        decoding="async"
        {{ $attributes }}
    >
@endif
