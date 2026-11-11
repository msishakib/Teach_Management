@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('site.pagination.label') }}" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="btn btn-ghost pointer-events-none opacity-40" aria-disabled="true"><x-heroicon-o-arrow-left class="size-4" aria-hidden="true" /> {{ __('site.pagination.previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-ghost"><x-heroicon-o-arrow-left class="size-4" aria-hidden="true" /> {{ __('site.pagination.previous') }}</a>
        @endif

        <p class="text-sm font-semibold text-muted">
            {{ __('site.pagination.page', ['current' => site_number($paginator->currentPage()), 'last' => site_number($paginator->lastPage())]) }}
        </p>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-ghost">{{ __('site.pagination.next') }} <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
        @else
            <span class="btn btn-ghost pointer-events-none opacity-40" aria-disabled="true">{{ __('site.pagination.next') }} <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></span>
        @endif
    </nav>
@endif
