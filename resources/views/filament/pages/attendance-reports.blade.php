<x-filament-panels::page>
    <form wire:submit.prevent="runReport" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Run report
        </x-filament::button>
    </form>

    @if ($this->reportType === 'monthly' && ! empty($this->report['students'] ?? []))
        <div class="mt-8 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Admission</th>
                        <th class="px-3 py-2 text-left">Student</th>
                        @foreach ($this->report['days'] as $day)
                            <th class="px-1 py-2 text-center text-xs">{{ \Illuminate\Support\Str::after($day, '-') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->report['students'] as $student)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $student->admission_no }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $student->name }}</td>
                            @foreach ($this->report['days'] as $day)
                                @php
                                    $status = $this->report['cells'][$student->id][$day] ?? null;
                                @endphp
                                <td class="px-1 py-2 text-center text-xs">
                                    {{ $status ? strtoupper(substr($status, 0, 1)) : '·' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (in_array($this->reportType, ['summary', 'absent_today', 'low'], true) && ($this->reportRows?->isNotEmpty() ?? false))
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
