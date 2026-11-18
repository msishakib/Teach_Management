@php
    $currency = (string) setting('general.currency_symbol', '৳');
@endphp
<x-layouts.site :title="'Fake payment — '.$order->order_no" :noindex="true">
    <section class="mx-auto max-w-xl px-4 py-12 sm:px-6">
        <h1 class="text-2xl font-extrabold">Fake gateway</h1>
        <p class="mt-2 text-sm text-muted">Local/test only. Order {{ $order->order_no }} · {{ $order->course?->name }}</p>
        <p class="mt-4 text-xl font-bold">{{ $currency }}{{ number_format((float) $order->net_amount, 2) }}</p>

        <form method="POST" action="{{ route('payments.fake.pay', ['order' => $order->order_no]) }}" class="mt-8 space-y-3">
            @csrf
            <label class="block text-sm font-semibold">
                Override amount (leave blank for correct amount)
                <input type="number" step="0.01" name="amount" class="mt-1 w-full rounded-xl border-black/10 dark:bg-zinc-900" placeholder="{{ $order->net_amount }}">
            </label>
            <div class="flex flex-wrap gap-2">
                <button name="outcome" value="success" class="btn btn-primary">Pay successfully</button>
                <button name="outcome" value="failed" class="btn">Fail</button>
                <button name="outcome" value="cancelled" class="btn">Cancel</button>
            </div>
        </form>
    </section>
</x-layouts.site>
