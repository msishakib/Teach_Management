@php
    $currency = (string) setting('general.currency_symbol', '৳');
@endphp
<x-layouts.site :title="'Payment successful'" :noindex="true">
    <section class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <h1 class="text-3xl font-extrabold text-emerald-600">Payment successful</h1>
        <p class="mt-3 text-muted">{{ $order->course?->name }}</p>
        <p class="mt-2 font-semibold">{{ $currency }}{{ number_format((float) $order->net_amount, 2) }} · {{ $order->order_no }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @if ($order->course)
                <a href="{{ route('courses.learn', ['course' => $order->course->code]) }}" class="btn btn-primary">Start learning</a>
            @endif
            @if ($order->receipt_id)
                <a href="{{ route('receipts.pdf', $order->receipt_id) }}" class="btn">Download receipt</a>
            @endif
        </div>
    </section>
</x-layouts.site>
