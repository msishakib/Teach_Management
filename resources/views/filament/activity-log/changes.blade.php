@php
    $changes = $activity->changes;
    $old = $changes->get('old', []) ?? [];
    $new = $changes->get('attributes', []) ?? [];
    $fields = collect(array_keys($old + $new))->sort()->values();

    $format = function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return (string) $value;
    };
@endphp

<div class="space-y-4 text-sm">
    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">User</div>
            <div class="font-medium">{{ $activity->causer?->name ?? 'System' }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Time</div>
            <div class="font-medium">{{ $activity->created_at?->format('d M Y H:i:s') }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Action</div>
            <div class="font-medium">{{ ucfirst($activity->event ?? $activity->description) }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Subject</div>
            <div class="font-medium">
                {{ $activity->subject_type ? class_basename($activity->subject_type) : '—' }}
                @if ($activity->subject_id)
                    #{{ $activity->subject_id }}
                @endif
            </div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">IP address</div>
            <div class="font-medium">{{ $activity->getExtraProperty('ip_address') ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">User agent</div>
            <div class="break-all font-medium">{{ $activity->getExtraProperty('user_agent') ?: '—' }}</div>
        </div>
    </div>

    @if ($fields->isEmpty())
        <p class="text-gray-500">No attribute values were recorded for this activity.</p>
    @else
        <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Field</th>
                        <th class="px-3 py-2 text-left font-semibold">Old value</th>
                        <th class="px-3 py-2 text-left font-semibold">New value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($fields as $field)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs">{{ $field }}</td>
                            <td class="px-3 py-2 break-all text-gray-600 dark:text-gray-400">{{ $format($old[$field] ?? null) }}</td>
                            <td class="px-3 py-2 break-all">{{ $format($new[$field] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
