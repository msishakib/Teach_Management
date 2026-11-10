<x-filament-panels::page>
    <form wire:submit.prevent="runReport" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Run report
            </x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="exportCsv">
                Export CSV
            </x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="exportPdf">
                Export PDF
            </x-filament::button>
        </div>
    </form>

    @if ($this->reportType === 'income_statement' && ! empty($this->report))
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <div class="overflow-x-auto rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="mb-3 font-semibold">Income</h3>
                <table class="min-w-full text-sm">
                    <tbody>
                        @foreach ($this->report['income'] as $row)
                            <tr class="border-t dark:border-white/10">
                                <td class="py-2">{{ $row->code }} — {{ $row->name }}</td>
                                <td class="py-2 text-right">{{ number_format((float) $row->balance, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t font-semibold dark:border-white/10">
                            <td class="py-2">Total income</td>
                            <td class="py-2 text-right">{{ number_format((float) $this->report['total_income'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="overflow-x-auto rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="mb-3 font-semibold">Expenses</h3>
                <table class="min-w-full text-sm">
                    <tbody>
                        @foreach ($this->report['expenses'] as $row)
                            <tr class="border-t dark:border-white/10">
                                <td class="py-2">{{ $row->code }} — {{ $row->name }}</td>
                                <td class="py-2 text-right">{{ number_format((float) $row->balance, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t font-semibold dark:border-white/10">
                            <td class="py-2">Total expense</td>
                            <td class="py-2 text-right">{{ number_format((float) $this->report['total_expense'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-4 text-lg font-semibold">Net profit/(loss): {{ number_format((float) $this->report['net_profit'], 2) }}</p>
    @endif

    @if ($this->reportType === 'balance_sheet' && ! empty($this->report))
        <div class="mt-8 grid gap-6 md:grid-cols-3">
            @foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label)
                <div class="overflow-x-auto rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="mb-3 font-semibold">{{ $label }}</h3>
                    <table class="min-w-full text-sm">
                        <tbody>
                            @foreach ($this->report[$key] as $row)
                                <tr class="border-t dark:border-white/10">
                                    <td class="py-2">{{ $row->code }} — {{ $row->name }}</td>
                                    <td class="py-2 text-right">{{ number_format((float) $row->balance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
        <div class="mt-4 space-y-1 text-sm">
            <div>Total assets: <strong>{{ number_format((float) $this->report['total_assets'], 2) }}</strong></div>
            <div>Total liabilities + equity: <strong>{{ number_format((float) $this->report['total_liabilities'] + (float) $this->report['total_equity'], 2) }}</strong></div>
            <div>Balanced: <strong>{{ ! empty($this->report['balanced']) ? 'Yes' : 'No' }}</strong></div>
        </div>
    @endif

    @if ($this->reportType === 'month_collection' && count($this->chartPoints))
        <div class="mt-8 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="mb-4 font-semibold">Month-wise collection</h3>
            <canvas id="collectionChart" height="120"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                (() => {
                    const points = @json($this->chartPoints);
                    const ctx = document.getElementById('collectionChart');
                    if (!ctx) return;
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: points.map(p => p.month),
                            datasets: [{
                                label: 'Collection',
                                data: points.map(p => p.total),
                                backgroundColor: '#0f766e',
                            }],
                        },
                        options: {
                            responsive: true,
                            scales: { y: { beginAtZero: true } },
                        },
                    });
                })();
            </script>
            <table class="mt-4 min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="py-2 text-left">Month</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->chartPoints as $point)
                        <tr class="border-t dark:border-white/10">
                            <td class="py-2">{{ $point['month'] }}</td>
                            <td class="py-2 text-right">{{ number_format((float) $point['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($this->reportType === 'trial_balance' && ! empty($this->report))
        <div class="mt-4 text-sm">
            Total debit: <strong>{{ number_format((float) ($this->report['total_debit'] ?? 0), 2) }}</strong>
            · Total credit: <strong>{{ number_format((float) ($this->report['total_credit'] ?? 0), 2) }}</strong>
        </div>
    @endif

    @if ($this->reportType === 'student_statement' && ! empty($this->report['student']))
        <div class="mt-4 text-sm">
            Student: <strong>{{ $this->report['student']->name }}</strong>
            ({{ $this->report['student']->admission_no }})
            · Closing due: <strong>{{ number_format((float) $this->report['closing_due'], 2) }}</strong>
        </div>
    @endif

    @if ($this->reportRows?->isNotEmpty())
        <div class="mt-8 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr>
                        @foreach (array_keys((array) $this->reportRows->first()) as $column)
                            <th class="px-3 py-2 text-left">{{ str_replace('_', ' ', ucfirst($column)) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->reportRows as $row)
                        <tr>
                            @foreach ((array) $row as $value)
                                <td class="px-3 py-2 whitespace-nowrap">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
