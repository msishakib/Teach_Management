<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    @if (auth()->user()?->guardian)
                        <th class="px-4 py-3">Student</th>
                    @endif
                    <th class="px-4 py-3">Course</th>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3">Session</th>
                    <th class="px-4 py-3">Enrolled</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->enrollments as $enrollment)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        @if (auth()->user()?->guardian)
                            <td class="px-4 py-3">{{ $enrollment->student?->name }}</td>
                        @endif
                        <td class="px-4 py-3 font-medium">{{ $enrollment->batch?->course?->name }}</td>
                        <td class="px-4 py-3">{{ $enrollment->batch?->name }}</td>
                        <td class="px-4 py-3">{{ $enrollment->batch?->session?->name }}</td>
                        <td class="px-4 py-3">{{ $enrollment->enrolled_on?->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $enrollment->status?->getLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-gray-500">No enrollments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->enrollments->links() }}</div>
</x-filament-panels::page>
