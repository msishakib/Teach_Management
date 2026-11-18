@php
    $currency = (string) setting('general.currency_symbol', '৳');
@endphp
<x-layouts.site :title="'Manual payment — '.$order->order_no" :noindex="true">
    <section class="mx-auto max-w-xl px-4 py-12 sm:px-6">
        <h1 class="text-2xl font-extrabold">Send money</h1>
        <p class="mt-2 text-muted">{{ $order->course?->name }} · Order {{ $order->order_no }}</p>
        <p class="mt-4 text-3xl font-extrabold">{{ $currency }}{{ number_format((float) $order->net_amount, 2) }}</p>

        <div class="mt-6 space-y-2 rounded-2xl bg-black/5 p-4 text-sm dark:bg-white/5">
            @if (! empty($meta['instructions']))
                <p>{{ $meta['instructions'] }}</p>
            @endif
            @if (! empty($meta['bkash_number']))
                <p><strong>bKash:</strong> {{ $meta['bkash_number'] }}</p>
            @endif
            @if (! empty($meta['nagad_number']))
                <p><strong>Nagad:</strong> {{ $meta['nagad_number'] }}</p>
            @endif
        </div>

        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-red-500/10 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('payments.manual.submit', ['order' => $order->order_no]) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block text-sm font-semibold">
                Sent via
                <select name="method" class="mt-1 w-full rounded-xl border-black/10 dark:bg-zinc-900" required>
                    <option value="bkash">bKash</option>
                    <option value="nagad">Nagad</option>
                </select>
            </label>
            <label class="block text-sm font-semibold">
                TrxID
                <input type="text" name="trx_id" value="{{ old('trx_id', $order->manual_trx_id) }}" class="mt-1 w-full rounded-xl border-black/10 dark:bg-zinc-900" required>
            </label>
            <button class="btn btn-primary w-full !py-3">Submit for verification</button>
        </form>
    </section>
</x-layouts.site>
