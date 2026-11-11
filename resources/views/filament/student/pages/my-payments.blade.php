<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    @if (auth()->user()?->guardian)
                        <th class="px-4 py-3">Student</th>
                    @endif
                    <th class="px-4 py-3">Receipt</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->receipts as $receipt)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        @if (auth()->user()?->guardian)
                            <td class="px-4 py-3">{{ $receipt->student?->name }}</td>
                        @endif
                        <td class="px-4 py-3 font-medium">{{ $receipt->receipt_no }}</td>
                        <td class="px-4 py-3">{{ $receipt->date?->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $receipt->method?->getLabel() }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $receipt->amount, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-filament::button
                                tag="a"
                                size="sm"
                                color="gray"
                                :href="route('receipts.pdf', $receipt)"
                                target="_blank"
                            >
                                Download
                            </x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-gray-500">No payments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->receipts->links() }}</div>
</x-filament-panels::page>
