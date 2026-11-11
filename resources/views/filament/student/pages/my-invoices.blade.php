<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    @if (auth()->user()?->guardian)
                        <th class="px-4 py-3">Student</th>
                    @endif
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Month</th>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Due</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->invoices as $invoice)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        @if (auth()->user()?->guardian)
                            <td class="px-4 py-3">{{ $invoice->student?->name }}</td>
                        @endif
                        <td class="px-4 py-3 font-medium">{{ $invoice->invoice_no }}</td>
                        <td class="px-4 py-3">{{ $invoice->month }}</td>
                        <td class="px-4 py-3">{{ $invoice->batch?->name }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $invoice->total, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $invoice->due, 2) }}</td>
                        <td class="px-4 py-3">{{ $invoice->status?->getLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-gray-500">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->invoices->links() }}</div>
</x-filament-panels::page>
