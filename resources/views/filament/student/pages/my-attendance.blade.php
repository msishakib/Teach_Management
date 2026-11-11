<x-filament-panels::page>
    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        @forelse ($this->summary as $status => $total)
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="text-xs uppercase text-gray-500">{{ str_replace('_', ' ', $status) }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $total }}</div>
            </div>
        @empty
            <div class="text-sm text-gray-500">No attendance records yet.</div>
        @endforelse
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    @if (auth()->user()?->guardian)
                        <th class="px-4 py-3">Student</th>
                    @endif
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $row)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        @if (auth()->user()?->guardian)
                            <td class="px-4 py-3">{{ $row->student?->name }}</td>
                        @endif
                        <td class="px-4 py-3">{{ $row->session?->date?->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $row->session?->batch?->name }}</td>
                        <td class="px-4 py-3">{{ $row->session?->subject?->name ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $row->status?->getLabel() ?? $row->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-gray-500">No attendance rows.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->rows->links() }}</div>
</x-filament-panels::page>
