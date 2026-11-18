@php
    $currency = (string) setting('general.currency_symbol', '৳');
@endphp
<x-layouts.site :title="__('Checkout').' — '.$course->name" :noindex="true">
    <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold tracking-tight">Checkout</h1>
        <p class="mt-2 text-muted">{{ $course->name }}</p>

        <div class="mt-8 space-y-6 rounded-3xl border border-black/5 bg-white/70 p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex items-baseline justify-between gap-4">
                <span class="font-semibold">Amount due</span>
                <span class="text-2xl font-extrabold">{{ $currency }}{{ number_format((float) $amount, 2) }}</span>
            </div>

            @if ($errors->any())
                <div class="rounded-xl bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('courses.checkout.store', ['course' => $course->code]) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                <label class="block text-sm font-semibold">
                    Payment method
                    <select name="gateway" class="mt-1 w-full rounded-xl border-black/10 dark:border-white/10 dark:bg-zinc-900">
                        @foreach ($drivers as $value => $label)
                            <option value="{{ $value }}" @selected($value === $defaultGateway)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm font-semibold">
                    Coupon code <span class="font-normal text-muted">(optional)</span>
                    <input type="text" name="coupon" value="{{ old('coupon') }}" class="mt-1 w-full rounded-xl border-black/10 dark:border-white/10 dark:bg-zinc-900" placeholder="SAVE10">
                </label>

                <button type="submit" class="btn btn-primary w-full !py-3.5 text-base">
                    Pay {{ $currency }}{{ number_format((float) $amount, 2) }}
                </button>
            </form>
        </div>
    </section>
</x-layouts.site>
