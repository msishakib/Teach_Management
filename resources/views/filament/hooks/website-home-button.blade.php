@php
    $homeUrl = route('home', [
        'locale' => \App\Http\Middleware\SetPublicLocale::preferred(request()),
    ]);
@endphp
<a
    href="{{ $homeUrl }}"
    class="fi-btn fi-btn-size-sm fi-btn-color-gray fi-btn-outline inline-flex items-center justify-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 hover:bg-gray-50 focus-visible:ring-2 dark:hover:bg-white/5"
>
    <x-heroicon-o-home class="h-5 w-5" aria-hidden="true" />
    <span>Home</span>
</a>
