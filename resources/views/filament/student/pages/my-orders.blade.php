<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Course</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Paid</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium">{{ $order->order_no }}</td>
                        <td class="px-4 py-3">{{ $order->course?->name }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $order->net_amount, 2) }}</td>
                        <td class="px-4 py-3">{{ $order->status?->getLabel() }}</td>
                        <td class="px-4 py-3">{{ $order->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($order->receipt_id)
                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    color="gray"
                                    :href="route('receipts.pdf', $order->receipt_id)"
                                    target="_blank"
                                >
                                    Receipt
                                </x-filament::button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-gray-500">No course orders yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->orders->links() }}</div>
</x-filament-panels::page>
