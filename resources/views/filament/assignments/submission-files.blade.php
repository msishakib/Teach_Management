<div class="space-y-2 text-sm">
    @forelse ($submission->files() as $file)
        <a
            href="{{ route('assignments.submissions.download', ['submission' => $submission, 'media' => $file]) }}"
            class="block rounded-lg border border-gray-200 px-3 py-2 font-medium text-primary-600 hover:bg-gray-50 dark:border-white/10"
            target="_blank"
        >
            {{ $file->displayName() }}
        </a>
    @empty
        <p class="text-gray-500">No files uploaded.</p>
    @endforelse

    @if (filled($submission->text))
        <div class="mt-4 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Written response</div>
            <div class="mt-2 whitespace-pre-wrap">{{ $submission->text }}</div>
        </div>
    @endif
</div>
