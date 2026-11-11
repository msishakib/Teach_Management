<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3">Course</th>
                    <th class="px-4 py-3">Session</th>
                    <th class="px-4 py-3">Students</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->batches as $batch)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium">{{ $batch->name }}</td>
                        <td class="px-4 py-3">{{ $batch->course?->name }}</td>
                        <td class="px-4 py-3">{{ $batch->session?->name }}</td>
                        <td class="px-4 py-3">{{ $batch->enrollments_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-filament::button
                                tag="a"
                                size="sm"
                                color="gray"
                                :href="\App\Filament\Teacher\Pages\BatchStudents::getUrl(['batch' => $batch->id])"
                            >
                                Students
                            </x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-gray-500">No assigned batches.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->batches->links() }}
    </div>
</x-filament-panels::page>
