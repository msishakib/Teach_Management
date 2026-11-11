@php
    $pageTitle = $title ? $title.' · '.$site['name'] : $site['name'].($site['tagline'] ? ' — '.$site['tagline'] : '');
    $pageDescription = \Illuminate\Support\Str::limit(strip_tags($description ?: ($site['description'] ?? '')), 170, '…');
    $shareImage = $image ?: $site['logo'];
    $ogLocales = ['bn' => 'bn_BD', 'en' => 'en_US'];
    $ogLocale = $ogLocales[app()->getLocale()] ?? 'en_US';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $canonical }}">
    @unless ($noindex)
        @foreach ($alternates as $altLocale => $altUrl)
            <link rel="alternate" hreflang="{{ $altLocale }}" href="{{ $altUrl }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ url('/') }}">
    @endunless
    <meta name="robots" content="{{ $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large' }}">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="{{ $site['primary'] }}">

    <meta property="og:type" content="{{ $type }}">
    <meta property="og:site_name" content="{{ $site['name'] }}">
    <meta property="og:title" content="{{ $title ?: $site['name'] }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $ogLocale }}">
    @foreach (array_diff_key($ogLocales, [app()->getLocale() => true]) as $altOgLocale)
        <meta property="og:locale:alternate" content="{{ $altOgLocale }}">
    @endforeach
    @if ($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
        <meta name="twitter:image" content="{{ $shareImage }}">
    @endif
    <meta name="twitter:card" content="{{ $shareImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title ?: $site['name'] }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">

    @if ($site['logo'])
        <link rel="icon" href="{{ $site['logo'] }}">
    @else
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='{{ rawurlencode($site['primary']) }}'/%3E%3Cpath d='M8 12l8-4 8 4-8 4z M11 15v5c0 1.5 2.2 3 5 3s5-1.5 5-3v-5l-5 2.5z' fill='white'/%3E%3C/svg%3E">
    @endif

    {{-- Theme tokens from Settings (values are validated hex colours). --}}
    <style>:root{--primary:{{ $site['primary'] }};--accent:{{ $site['accent'] }}}</style>

    {{-- Apply saved/system theme before first paint to avoid a flash. --}}
    <script>
        (function () {
            var root = document.documentElement;
            try {
                var saved = localStorage.getItem('theme');
                var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                root.classList.toggle('dark', dark);
            } catch (e) {}
            root.classList.add('js');
            // Safety net: never leave content hidden if the bundle fails to load.
            setTimeout(function () { if (!root.hasAttribute('data-ready')) { root.classList.add('reveal-fallback'); } }, 4000);
        })();
    </script>

    @vite(['resources/css/site.css', 'resources/js/site.js'])

    @stack('head')
</head>
<body class="min-h-dvh antialiased">
    <a href="#main" class="sr-only z-[100] rounded-full bg-primary px-5 py-3 font-semibold text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">{{ __('site.nav.skip') }}</a>

    <div class="mesh-bg" aria-hidden="true"></div>

    <x-navbar :site="$site" />

    <main id="main" tabindex="-1">
        {{ $slot }}
    </main>

    <x-footer :site="$site" />

    @stack('scripts')
</body>
</html>
