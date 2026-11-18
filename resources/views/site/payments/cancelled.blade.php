<x-layouts.site :title="'Payment cancelled'" :noindex="true">
    <section class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <h1 class="text-3xl font-extrabold">Payment cancelled</h1>
        <p class="mt-3 text-muted">
            @if ($order)
                Order {{ $order->order_no }} was cancelled. No charge was completed.
            @else
                The payment was cancelled.
            @endif
        </p>
        @if ($order?->course)
            <a href="{{ route('courses.show', ['course' => $order->course->code]) }}" class="btn btn-primary mt-8">Back to course</a>
        @endif
    </section>
</x-layouts.site>
