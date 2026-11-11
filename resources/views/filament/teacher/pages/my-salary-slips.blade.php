<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3">Month</th>
                    <th class="px-4 py-3">Basic</th>
                    <th class="px-4 py-3">Bonus</th>
                    <th class="px-4 py-3">Deductions</th>
                    <th class="px-4 py-3">Net</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Voucher</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->salaries as $salary)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium">{{ $salary->month }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $salary->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $salary->bonus, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $salary->deductions, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $salary->net, 2) }}</td>
                        <td class="px-4 py-3">{{ $salary->status?->getLabel() }}</td>
                        <td class="px-4 py-3">{{ $salary->paymentVoucher?->voucher_no ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-gray-500">No salary slips yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->salaries->links() }}
    </div>
</x-filament-panels::page>
