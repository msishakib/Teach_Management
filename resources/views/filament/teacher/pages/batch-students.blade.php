<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
        {{ $batch->name }} · {{ $batch->course?->name }} · {{ $batch->session?->name }}
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3">Admission no</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->students as $student)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3">{{ $student->admission_no }}</td>
                        <td class="px-4 py-3 font-medium">{{ $student->name }}</td>
                        <td class="px-4 py-3">{{ $student->phone ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $student->status?->getLabel() ?? $student->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-gray-500">No active students in this batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->students->links() }}
    </div>
</x-filament-panels::page>
