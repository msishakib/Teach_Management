<x-layouts.site :title="'TrxID submitted'" :noindex="true">
    <section class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <h1 class="text-2xl font-extrabold">Submitted</h1>
        <p class="mt-3 text-muted">
            Your TrxID for order {{ $order->order_no }} is awaiting admin verification.
            Course access will unlock once payment is confirmed.
        </p>
        <a href="{{ route('courses.show', ['course' => $order->course?->code]) }}" class="btn btn-primary mt-8">Back to course</a>
    </section>
</x-layouts.site>
