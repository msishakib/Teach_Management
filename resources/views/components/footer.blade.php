@props(['site'])

@php
    $explore = app(\App\Services\MenuService::class)->links(\App\Enums\MenuLocation::Footer);
    $account = [
        [route('apply'), __('site.nav.apply')],
        [route('filament.student.auth.login'), __('site.nav.student_login')],
        [route('filament.teacher.auth.login'), __('site.nav.teacher_login')],
        [route('filament.admin.auth.login'), __('site.nav.admin_login')],
    ];
    $networkLabels = ['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'twitter' => 'X', 'whatsapp' => 'WhatsApp'];
@endphp

<footer class="relative mt-8 border-t border-line" aria-label="{{ __('site.footer.label') }}">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] lg:px-8">
        <div class="space-y-4">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                @if ($site['logo'])
                    <img src="{{ $site['logo'] }}" alt="" width="40" height="40" loading="lazy" decoding="async" class="size-10 rounded-xl object-contain">
                @endif
                <span class="text-xl font-extrabold tracking-tight">{{ $site['name'] }}</span>
            </a>
            @if ($site['tagline'])
                <p class="max-w-sm text-sm leading-relaxed text-muted">{{ $site['tagline'] }}</p>
            @endif

            @if ($site['socials'])
                <ul class="flex flex-wrap gap-2" role="list">
                    @foreach ($site['socials'] as $network => $url)
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                               class="glass grid size-10 place-items-center rounded-full text-ink transition hover:-translate-y-0.5 hover:text-primary-ink"
                               aria-label="{{ $networkLabels[$network] ?? ucfirst($network) }}">
                                <x-social-icon :network="$network" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($explore)
            <div>
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-muted">{{ __('site.footer.explore') }}</h2>
                <ul class="space-y-2.5 text-sm font-medium" role="list">
                    @foreach ($explore as $link)
                        <li><a href="{{ $link['href'] }}" @if ($link['new_tab']) target="_blank" rel="noopener" @endif @if ($link['current']) aria-current="page" @endif class="transition-colors hover:text-primary-ink">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-muted">{{ __('site.footer.account') }}</h2>
            <ul class="space-y-2.5 text-sm font-medium" role="list">
                @foreach ($account as [$href, $label])
                    <li><a href="{{ $href }}" class="transition-colors hover:text-primary-ink">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-muted">{{ __('site.footer.contact') }}</h2>
            <address class="space-y-3 text-sm not-italic text-muted">
                @if ($site['address'])
                    <p class="flex gap-2"><x-heroicon-o-map-pin class="mt-0.5 size-4 shrink-0 text-primary-ink" />{{ $site['address'] }}</p>
                @endif
                @if ($site['phone'])
                    <p class="flex gap-2"><x-heroicon-o-phone class="mt-0.5 size-4 shrink-0 text-primary-ink" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $site['phone']) }}" class="hover:text-ink">{{ $site['phone'] }}</a></p>
                @endif
                @if ($site['email'])
                    <p class="flex gap-2"><x-heroicon-o-envelope class="mt-0.5 size-4 shrink-0 text-primary-ink" /><a href="mailto:{{ $site['email'] }}" class="break-all hover:text-ink">{{ $site['email'] }}</a></p>
                @endif
            </address>
        </div>
    </div>

    <div class="border-t border-line">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-muted sm:flex-row sm:px-6 lg:px-8">
            <p>&copy; {{ site_number((int) now()->format('Y'), grouping: false) }} {{ $site['name'] }}. {{ __('site.footer.rights') }}</p>
            <p class="flex items-center gap-1.5">{{ __('site.footer.built') }} <x-heroicon-s-heart class="size-4 text-rose-500" aria-hidden="true" /></p>
        </div>
    </div>
</footer>
