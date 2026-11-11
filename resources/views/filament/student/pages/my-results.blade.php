<x-filament-panels::page>
    @if ($this->results->isEmpty())
        <p class="text-sm text-gray-500">No published results are available yet.</p>
    @else
        <div class="space-y-6">
            @foreach ($this->results as $result)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold">{{ $result->exam?->name }}</h3>
                            <p class="text-sm text-gray-500">
                                @if (auth()->user()?->guardian)
                                    {{ $result->student?->name }} ·
                                @endif
                                {{ $result->exam?->examType?->name }}
                                · GPA {{ number_format((float) $result->gpa, 2) }} ({{ $result->letter_grade }})
                                · Rank {{ $result->rank_in_batch ?? '—' }}
                                · {{ $result->status->getLabel() }}
                            </p>
                        </div>
                        <x-filament::button
                            tag="a"
                            size="sm"
                            :href="route('exam-results.marksheet.pdf', $result)"
                            target="_blank"
                        >
                            Marksheet PDF
                        </x-filament::button>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1 pr-3">Subject</th>
                                    <th class="py-1 pr-3">Marks</th>
                                    <th class="py-1 pr-3">Grade</th>
                                    <th class="py-1">Pass</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result->items as $item)
                                    <tr>
                                        <td class="py-1 pr-3">{{ $item->examSubject?->subject?->name }}</td>
                                        <td class="py-1 pr-3">
                                            @if ($item->is_absent)
                                                Absent
                                            @else
                                                {{ number_format((float) $item->marks_obtained, 2) }}
                                            @endif
                                        </td>
                                        <td class="py-1 pr-3">{{ $item->letter_grade }}</td>
                                        <td class="py-1">{{ $item->is_pass ? 'Yes' : 'No' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
