<x-layouts.site :title="'Payment failed'" :noindex="true">
    <section class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <h1 class="text-3xl font-extrabold text-red-600">Payment failed</h1>
        <p class="mt-3 text-muted">
            @if ($order)
                No course access was granted for order {{ $order->order_no }}.
            @else
                The payment could not be completed.
            @endif
        </p>
        @if ($order?->course)
            <a href="{{ route('courses.checkout', ['course' => $order->course->code]) }}" class="btn btn-primary mt-8">Try again</a>
        @endif
    </section>
</x-layouts.site>
