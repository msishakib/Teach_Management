<x-layouts.site :title="$quiz->title" :description="$course->short_description" :noindex="true">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('courses.learn', ['course' => $course->code]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted transition hover:text-primary-ink">
            <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
            {{ __('course.player.back') }}
        </a>

        <h1 class="mt-4 text-3xl font-extrabold tracking-tight">{{ $quiz->title }}</h1>
        <p class="mt-2 text-sm text-muted">{{ $course->name }}</p>

        @if (session('status'))
            <div class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm font-semibold text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 space-y-3 rounded-3xl border border-black/5 bg-black/[0.02] p-5 text-sm dark:border-white/10 dark:bg-white/[0.03]">
            <p>{{ __('course.quiz.attempts', ['used' => site_number($attemptsUsed), 'max' => site_number($quiz->max_attempts)]) }}</p>
            <p>{{ __('course.quiz.pass_percent', ['percent' => site_number($quiz->pass_percent)]) }}</p>
            @if ($quiz->time_limit_minutes)
                <p>{{ __('course.quiz.time_limit', ['minutes' => site_number($quiz->time_limit_minutes)]) }}</p>
            @endif
            <p>{{ site_number($quiz->questions_count) }} questions</p>
        </div>

        @if ($results)
            <div class="mt-6 rounded-3xl border border-black/5 p-5 dark:border-white/10">
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-muted">{{ __('course.quiz.results') }}</h2>
                <p class="mt-2 text-lg font-bold">
                    {{ __('course.quiz.score', ['score' => site_number($results['attempt']['score']), 'percent' => site_number($results['attempt']['percent'])]) }}
                    —
                    {{ $results['attempt']['passed'] ? __('course.quiz.passed') : __('course.quiz.failed') }}
                </p>
            </div>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            @if ($openAttempt)
                <a href="{{ route('courses.quizzes.attempts.show', ['course' => $course->code, 'quiz' => $quiz, 'attempt' => $openAttempt]) }}" class="btn btn-primary">
                    {{ __('course.quiz.continue') }}
                </a>
            @elseif ($attemptsUsed < $quiz->max_attempts && $quiz->isAvailable())
                <form method="post" action="{{ route('courses.quizzes.start', ['course' => $course->code, 'quiz' => $quiz]) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('course.quiz.start') }}</button>
                </form>
            @elseif (! $quiz->isAvailable())
                <p class="text-sm font-semibold text-muted">{{ __('course.quiz.unavailable') }}</p>
            @else
                <p class="text-sm font-semibold text-muted">{{ __('course.quiz.no_attempts') }}</p>
            @endif

            @if ($latestAttempt && $latestAttempt->status->isClosed())
                <a href="{{ route('courses.quizzes.attempts.show', ['course' => $course->code, 'quiz' => $quiz, 'attempt' => $latestAttempt]) }}" class="btn btn-ghost">
                    {{ __('course.quiz.results') }}
                </a>
            @endif
        </div>
    </div>
</x-layouts.site>
