@props(['network'])

<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($network)
        @case('facebook')
            <path d="M14 8.5h2.2V5.2H13.6C11 5.2 9.8 6.8 9.8 9v2.3H7.6v3.3h2.2V21h3.4v-6.4h2.4l.4-3.3h-2.8V9.3c0-.5.3-.8.8-.8z" fill="currentColor" stroke="none" />
            @break
        @case('youtube')
            <rect x="2.5" y="5.5" width="19" height="13" rx="4" />
            <path d="M10 9.5v5l4.5-2.5z" fill="currentColor" stroke="none" />
            @break
        @case('instagram')
            <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
            <circle cx="12" cy="12" r="3.8" />
            <circle cx="17" cy="7" r=".9" fill="currentColor" stroke="none" />
            @break
        @case('linkedin')
            <rect x="4" y="9.5" width="3.2" height="10.5" fill="currentColor" stroke="none" />
            <circle cx="5.6" cy="5.8" r="1.9" fill="currentColor" stroke="none" />
            <path d="M10.3 9.5h3.1v1.5c.6-1 1.7-1.8 3.4-1.8 3 0 3.7 2 3.7 4.5V20h-3.2v-5.6c0-1.3-.1-2.5-1.7-2.5s-2 1.2-2 2.5V20h-3.3z" fill="currentColor" stroke="none" />
            @break
        @case('twitter')
            <path d="M4.5 4.5l15 15M19.5 4.5l-15 15" />
            @break
        @case('whatsapp')
            <path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.3L3.2 20.8l4.4-1.1A8.8 8.8 0 1 0 12 3.2z" />
            <path d="M9.2 8.4c-.2.6-.1 1.5.9 2.9 1 1.3 2.2 2.2 3.3 2.5.8.2 1.4-.2 1.7-.9l-1.5-.9-.7.6c-.7-.3-1.6-1.1-2-2l.6-.7-.8-1.6z" fill="currentColor" stroke="none" />
            @break
    @endswitch
</svg>
