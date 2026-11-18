<div class="space-y-4 text-sm">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Attempts</div>
            <div class="mt-1 text-lg font-bold">{{ $stats['attempts'] }}</div>
        </div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Average %</div>
            <div class="mt-1 text-lg font-bold">{{ $stats['average_percent'] ?? '—' }}</div>
        </div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pass rate %</div>
            <div class="mt-1 text-lg font-bold">{{ $stats['pass_rate'] ?? '—' }}</div>
        </div>
    </div>

    <div>
        <h3 class="font-semibold">Hardest questions</h3>
        @forelse ($stats['hardest_questions'] as $row)
            <div class="mt-2 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="font-medium">{{ \Illuminate\Support\Str::limit($row['text'], 120) }}</div>
                <div class="mt-1 text-xs text-gray-500">Correct rate: {{ $row['correct_rate'] }}% ({{ $row['answered'] }} answers)</div>
            </div>
        @empty
            <p class="mt-2 text-gray-500">No graded answers yet.</p>
        @endforelse
    </div>
</div>
