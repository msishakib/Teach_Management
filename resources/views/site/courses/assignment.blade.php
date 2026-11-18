<x-layouts.site :title="$assignment->title" :noindex="true">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('courses.learn', ['course' => $course->code]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted transition hover:text-primary-ink">
            <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
            {{ __('course.player.back') }}
        </a>

        <h1 class="mt-4 text-3xl font-extrabold tracking-tight">{{ $assignment->title }}</h1>
        <p class="mt-2 text-sm text-muted">{{ $course->name }}</p>

        @if (session('status'))
            <div class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm font-semibold text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 space-y-2 rounded-3xl border border-black/5 bg-black/[0.02] p-5 text-sm dark:border-white/10 dark:bg-white/[0.03]">
            @if ($assignment->due_at)
                <p>{{ __('course.assignment.due', ['date' => $assignment->due_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}</p>
            @endif
            <p>{{ __('course.assignment.max_marks', ['marks' => site_number($assignment->max_marks)]) }}</p>
            <p>{{ __('course.assignment.max_files', ['count' => site_number($assignment->max_files)]) }}</p>
            @if ($isLate && ! $assignment->allow_late)
                <p class="font-semibold text-red-700">{{ __('course.assignment.late_blocked') }}</p>
            @elseif ($isLate)
                <p class="font-semibold text-amber-700">{{ __('course.assignment.late_allowed') }}</p>
            @endif
        </div>

        @if (filled($assignment->instructions))
            <div class="prose-site mt-6 max-w-none text-sm sm:text-base">{!! nl2br(e($assignment->instructions)) !!}</div>
        @endif

        @if ($submission)
            <div class="mt-6 rounded-3xl border border-black/5 p-5 dark:border-white/10">
                <p class="text-sm font-semibold">{{ __('course.assignment.status', ['status' => $submission->status->getLabel()]) }}</p>
                @if ($submission->is_late)
                    <p class="mt-1 text-xs font-bold uppercase tracking-wider text-amber-700">{{ __('course.assignment.late') }}</p>
                @endif
                @if ($submission->marks !== null)
                    <p class="mt-2 text-sm">{{ __('course.assignment.marks', ['marks' => site_number($submission->marks), 'max' => site_number($assignment->max_marks)]) }}</p>
                @endif
                @if (filled($submission->feedback))
                    <div class="mt-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-muted">{{ __('course.assignment.feedback') }}</p>
                        <p class="mt-1 text-sm">{{ $submission->feedback }}</p>
                    </div>
                @endif
                @if ($submission->files()->isNotEmpty())
                    <ul class="mt-4 space-y-2">
                        @foreach ($submission->files() as $file)
                            <li>
                                <a class="text-sm font-semibold text-primary-ink underline" href="{{ route('assignments.submissions.download', ['submission' => $submission, 'media' => $file]) }}">
                                    {{ $file->displayName() }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if ($canSubmit)
            <form method="post" action="{{ route('courses.assignments.submit', ['course' => $course->code, 'assignment' => $assignment]) }}" enctype="multipart/form-data" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label class="text-sm font-bold" for="text">{{ __('course.assignment.text') }}</label>
                    <textarea id="text" name="text" rows="6" class="mt-2 w-full rounded-2xl border border-black/10 bg-transparent p-3 text-sm">{{ old('text', $submission?->text) }}</textarea>
                    @error('text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-bold" for="files">{{ __('course.assignment.files') }}</label>
                    <input id="files" type="file" name="files[]" multiple class="mt-2 block w-full text-sm" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    @error('files') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('files.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    {{ $submission ? __('course.assignment.resubmit') : __('course.assignment.submit') }}
                </button>
            </form>
        @endif
    </div>
</x-layouts.site>
