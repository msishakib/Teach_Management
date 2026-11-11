@props(['site'])

@php
    $links = app(\App\Services\MenuService::class)->links(\App\Enums\MenuLocation::Header);
    // More links than this would overflow the desktop bar, so the rest go under "More".
    $visibleLinks = array_slice($links, 0, 6);
    $moreLinks = array_slice($links, 6);
    $logins = [
        ['href' => route('filament.student.auth.login'), 'label' => __('site.nav.student_login'), 'icon' => 'academic-cap'],
        ['href' => route('filament.teacher.auth.login'), 'label' => __('site.nav.teacher_login'), 'icon' => 'user-group'],
        ['href' => route('filament.admin.auth.login'), 'label' => __('site.nav.admin_login'), 'icon' => 'shield-check'],
    ];
    $langUrls = \App\Support\LocalizedUrl::alternates();
    $initials = \Illuminate\Support\Str::of($site['name'])->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp

<header
    x-data="navbar"
    @scroll.window.passive="onScroll()"
    @keydown.escape.window="open = false; loginOpen = false"
    class="sticky top-0 z-50 transition-[padding,box-shadow] duration-300"
    :class="scrolled ? 'shadow-[0_8px_30px_-12px_rgb(20_22_60/0.25)]' : ''"
>
    <div class="glass-strong absolute inset-0 border-x-0 border-t-0" aria-hidden="true"></div>

    <div
        class="relative mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 transition-[padding] duration-300 sm:px-6 lg:px-8"
        :class="scrolled ? 'py-2' : 'py-3.5 xl:py-4'"
    >
        {{-- Brand --}}
        <a href="{{ route('home') }}" class="flex min-w-0 shrink items-center gap-3 rounded-xl" aria-label="{{ $site['name'] }}">
            @if ($site['logo'])
                <img src="{{ $site['logo'] }}" alt="" width="40" height="40" decoding="async"
                     class="size-10 shrink-0 rounded-xl object-contain transition-transform duration-300"
                     :class="scrolled ? 'scale-90' : ''">
            @else
                <span class="grad-bg grid size-10 shrink-0 place-items-center rounded-xl text-sm font-extrabold text-white shadow-lg transition-transform duration-300"
                      :class="scrolled ? 'scale-90' : ''" aria-hidden="true">{{ $initials }}</span>
            @endif
            <span class="truncate text-base font-extrabold tracking-tight sm:text-lg">{{ $site['name'] }}</span>
        </a>

        {{-- Desktop links --}}
        <nav class="hidden items-center gap-0.5 xl:flex" aria-label="{{ __('site.nav.primary') }}">
            @foreach ($visibleLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['new_tab']) target="_blank" rel="noopener" @endif @if ($link['current']) aria-current="page" @endif
                   class="whitespace-nowrap rounded-full px-3 py-2 text-sm font-semibold transition-colors hover:bg-primary/10 hover:text-ink {{ $link['current'] ? 'text-ink' : 'text-muted' }}">{{ $link['label'] }}</a>
            @endforeach
            @if ($moreLinks)
                <div class="relative" x-data="{ more: false }" @click.outside="more = false" @keydown.escape="more = false">
                    <button type="button" @click="more = !more" :aria-expanded="more" aria-haspopup="true"
                            class="flex items-center gap-1 whitespace-nowrap rounded-full px-3 py-2 text-sm font-semibold text-muted transition-colors hover:bg-primary/10 hover:text-ink">
                        {{ __('site.nav.more') }}
                        <x-heroicon-o-chevron-down class="size-4 transition-transform" ::class="more ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="more" x-cloak x-transition.origin.top.duration.150ms
                         class="glass-strong absolute left-0 mt-2 w-56 rounded-2xl p-2 shadow-lift">
                        @foreach ($moreLinks as $link)
                            <a href="{{ $link['href'] }}" @click="more = false" @if ($link['new_tab']) target="_blank" rel="noopener" @endif @if ($link['current']) aria-current="page" @endif
                               class="block rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors hover:bg-primary/10">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>

        <div class="flex items-center gap-2">
            {{-- Language toggle --}}
            <div class="glass hidden items-center rounded-full p-0.5 text-xs font-bold sm:flex" role="group" aria-label="{{ __('site.nav.language') }}">
                <a href="{{ $langUrls['en'] }}" lang="en" hreflang="en" @if (app()->getLocale() === 'en') aria-current="true" @endif
                   class="rounded-full px-2.5 py-1.5 transition-colors {{ app()->getLocale() === 'en' ? 'bg-primary text-white' : 'text-muted hover:text-ink' }}">EN</a>
                <a href="{{ $langUrls['bn'] }}" lang="bn" hreflang="bn" @if (app()->getLocale() === 'bn') aria-current="true" @endif
                   class="rounded-full px-2.5 py-1.5 transition-colors {{ app()->getLocale() === 'bn' ? 'bg-primary text-white' : 'text-muted hover:text-ink' }}">বাংলা</a>
            </div>

            {{-- Theme toggle --}}
            <button type="button" x-data="themeToggle" @click="toggle()"
                    class="glass grid size-10 place-items-center rounded-full text-ink transition-transform hover:scale-105"
                    :aria-label="dark ? @js(__('site.nav.light_mode')) : @js(__('site.nav.dark_mode'))">
                <x-heroicon-o-sun class="size-5" x-show="dark" x-cloak />
                <x-heroicon-o-moon class="size-5" x-show="!dark" />
            </button>

            {{-- Login dropdown (desktop) --}}
            <div class="relative hidden xl:block" @click.outside="loginOpen = false">
                <button type="button" @click="loginOpen = !loginOpen" :aria-expanded="loginOpen" aria-haspopup="true"
                        class="btn btn-ghost !py-2.5">
                    {{ __('site.nav.login') }}
                    <x-heroicon-o-chevron-down class="size-4 transition-transform" ::class="loginOpen ? 'rotate-180' : ''" />
                </button>
                <div x-show="loginOpen" x-cloak x-transition.origin.top.right.duration.150ms
                     class="glass-strong absolute right-0 mt-2 w-60 rounded-2xl p-2 shadow-lift">
                    @foreach ($logins as $login)
                        <a href="{{ $login['href'] }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors hover:bg-primary/10">
                            <span class="grid size-8 place-items-center rounded-lg bg-primary/10 text-primary-ink">
                                <x-dynamic-component :component="'heroicon-o-'.$login['icon']" class="size-4" />
                            </span>
                            {{ $login['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('apply') }}" class="btn btn-primary hidden !py-2.5 sm:inline-flex">{{ __('site.nav.apply') }}</a>

            {{-- Burger --}}
            <button type="button" x-ref="burger" @click="open = true" :aria-expanded="open" aria-controls="mobile-menu"
                    class="glass grid size-10 place-items-center rounded-full xl:hidden" aria-label="{{ __('site.nav.open_menu') }}">
                <x-heroicon-o-bars-3 class="size-5" />
            </button>
        </div>
    </div>

    {{-- Mobile slide-in menu --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] xl:hidden" role="dialog" aria-modal="true" aria-label="{{ __('site.nav.menu') }}">
        <div x-show="open" x-transition.opacity.duration.200ms @click="open = false" class="absolute inset-0 bg-black/50"></div>

        <div id="mobile-menu"
             x-show="open"
             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="absolute inset-y-0 right-0 flex w-[min(22rem,88vw)] flex-col gap-6 overflow-y-auto bg-surface-solid p-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <span class="text-lg font-extrabold">{{ __('site.nav.menu') }}</span>
                <button type="button" x-ref="close" @click="open = false" class="glass grid size-10 place-items-center rounded-full" aria-label="{{ __('site.nav.close_menu') }}">
                    <x-heroicon-o-x-mark class="size-5" />
                </button>
            </div>

            <nav class="flex flex-col gap-1" aria-label="{{ __('site.nav.primary') }}">
                @foreach ($links as $link)
                    <a href="{{ $link['href'] }}" @click="open = false" @if ($link['new_tab']) target="_blank" rel="noopener" @endif @if ($link['current']) aria-current="page" @endif
                       class="rounded-xl px-4 py-3 text-lg font-semibold transition-colors hover:bg-primary/10">{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="flex flex-col gap-2">
                <p class="px-1 text-xs font-bold uppercase tracking-wider text-muted">{{ __('site.nav.login') }}</p>
                @foreach ($logins as $login)
                    <a href="{{ $login['href'] }}" class="btn btn-ghost !justify-start">
                        <x-dynamic-component :component="'heroicon-o-'.$login['icon']" class="size-5 text-primary-ink" />
                        {{ $login['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="mt-auto flex flex-col gap-4">
                <div class="glass flex items-center justify-center rounded-full p-1 text-sm font-bold" role="group" aria-label="{{ __('site.nav.language') }}">
                    <a href="{{ $langUrls['en'] }}" lang="en" hreflang="en" class="flex-1 rounded-full px-4 py-2 text-center {{ app()->getLocale() === 'en' ? 'bg-primary text-white' : 'text-muted' }}">English</a>
                    <a href="{{ $langUrls['bn'] }}" lang="bn" hreflang="bn" class="flex-1 rounded-full px-4 py-2 text-center {{ app()->getLocale() === 'bn' ? 'bg-primary text-white' : 'text-muted' }}">বাংলা</a>
                </div>
                <a href="{{ route('apply') }}" class="btn btn-primary w-full">{{ __('site.nav.apply') }}</a>
            </div>
        </div>
    </div>
</header>
