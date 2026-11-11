<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3">Day</th>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Teacher</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->routines as $routine)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3">{{ $routine->day?->getLabel() }}</td>
                        <td class="px-4 py-3">
                            {{ \Illuminate\Support\Str::of((string) $routine->start_time)->substr(0, 5) }}
                            –
                            {{ \Illuminate\Support\Str::of((string) $routine->end_time)->substr(0, 5) }}
                        </td>
                        <td class="px-4 py-3">{{ $routine->batch?->name }}</td>
                        <td class="px-4 py-3">{{ $routine->subject?->name }}</td>
                        <td class="px-4 py-3">{{ $routine->teacher?->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-gray-500">No routine found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->routines->links() }}</div>
</x-filament-panels::page>
