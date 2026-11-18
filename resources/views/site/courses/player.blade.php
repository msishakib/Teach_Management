@php
    $type = $lesson->type ?? \App\Enums\LessonType::Video;
    $currentSectionIndex = null;

    foreach ($curriculum as $sectionIndex => $section) {
        foreach ($section['lessons'] as $item) {
            if ((int) $item['id'] === (int) $lesson->getKey()) {
                $currentSectionIndex = $sectionIndex;
                break 2;
            }
        }
    }

    $resources = $lesson->resources->filter(fn ($resource) => $resource->file() !== null);
    $locked = fn (int $id): bool => in_array($id, $lockedLessonIds, true);
@endphp

<x-layouts.site
    :title="$lesson->title"
    :description="$course->short_description ?: $course->description"
    :noindex="true"
>
    @push('head')
        <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css">
    @endpush

    @push('scripts')
        <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
        <script>
            (function () {
                var video = document.getElementById('lesson-video');
                if (video && window.Plyr) {
                    new Plyr(video, { ratio: '16:9' });
                }
            })();
        </script>
    @endpush

    <div
        x-data="{ sidebarOpen: false, openSections: [@js($currentSectionIndex ?? 0)] }"
        class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
    >
        {{-- Top bar --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ route('courses.show', ['course' => $course->code]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted transition hover:text-primary-ink">
                    <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
                    {{ __('course.player.back') }}
                </a>
                <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight sm:text-2xl">{{ $course->name }}</h1>
            </div>

            @if ($lessonNumber !== null)
                <span class="rounded-full bg-primary/10 px-3 py-1.5 text-sm font-bold text-primary-ink">
                    {{ __('course.player.lesson', ['number' => site_number($lessonNumber)]) }}
                    <span class="font-semibold text-muted">/ {{ site_number($totalLessons) }}</span>
                </span>
            @endif
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
            {{-- Curriculum sidebar --}}
            <aside
                :class="sidebarOpen ? 'block' : 'hidden lg:block'"
                class="lg:sticky lg:top-24"
                aria-label="{{ __('course.player.content') }}"
            >
                <div class="glass overflow-hidden rounded-3xl">
                    <div class="flex items-center justify-between gap-3 border-b border-black/5 px-5 py-4 dark:border-white/10">
                        <h2 class="text-sm font-extrabold uppercase tracking-wider text-muted">{{ __('course.player.content') }}</h2>
                        <span class="text-xs font-semibold text-muted">{{ __('course.curriculum.lessons', ['count' => site_number($totalLessons)]) }}</span>
                    </div>

                    <div class="max-h-[calc(100dvh-12rem)] overflow-y-auto p-2">
                        @foreach ($curriculum as $sectionIndex => $section)
                            <div class="mb-1">
                                <button
                                    type="button"
                                    @click="openSections.includes({{ $sectionIndex }}) ? openSections = openSections.filter((value) => value !== {{ $sectionIndex }}) : openSections.push({{ $sectionIndex }})"
                                    class="flex w-full items-center justify-between gap-2 rounded-2xl px-3 py-2.5 text-left transition hover:bg-primary/5"
                                    :aria-expanded="openSections.includes({{ $sectionIndex }}) ? 'true' : 'false'"
                                >
                                    <span class="min-w-0">
                                        <span class="block text-[0.65rem] font-bold uppercase tracking-wider text-muted">{{ __('course.curriculum.section') }} {{ site_number($sectionIndex + 1) }}</span>
                                        <span class="mt-0.5 block truncate text-sm font-extrabold">{{ $section['title'] }}</span>
                                    </span>
                                    <x-heroicon-o-chevron-down class="size-4 shrink-0 text-muted transition-transform" ::class="openSections.includes({{ $sectionIndex }}) ? 'rotate-180' : ''" aria-hidden="true" />
                                </button>

                                <ol x-show="openSections.includes({{ $sectionIndex }})" class="space-y-0.5 pb-2" role="list">
                                    @forelse ($section['lessons'] as $item)
                                        @php
                                            $isCurrent = (int) $item['id'] === (int) $lesson->getKey();
                                            $isLocked = $locked((int) $item['id']);
                                        @endphp
                                        <li>
                                            @if ($isLocked)
                                                <div class="flex items-center gap-2.5 rounded-2xl px-3 py-2.5 opacity-60" title="{{ __('course.player.locked', ['days' => site_number($item['drip_after_days'])]) }}">
                                                    <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-black/5 text-muted dark:bg-white/10">
                                                        <x-heroicon-o-lock-closed class="size-4" aria-hidden="true" />
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-semibold">{{ $item['title'] }}</span>
                                                        <span class="block text-[0.7rem] text-muted">{{ __('course.player.locked', ['days' => site_number($item['drip_after_days'])]) }}</span>
                                                    </span>
                                                </div>
                                            @else
                                                <a
                                                    href="{{ route('courses.learn', ['course' => $course->code, 'lesson' => $item['id']]) }}"
                                                    @class([
                                                        'flex items-center gap-2.5 rounded-2xl px-3 py-2.5 transition',
                                                        'bg-primary/10 text-primary-ink' => $isCurrent,
                                                        'hover:bg-primary/5' => ! $isCurrent,
                                                    ])
                                                    @if ($isCurrent) aria-current="page" @endif
                                                >
                                                    <span @class([
                                                        'grid size-8 shrink-0 place-items-center rounded-xl',
                                                        'bg-primary text-white' => $isCurrent,
                                                        'bg-black/5 text-muted dark:bg-white/10' => ! $isCurrent,
                                                    ])>
                                                        <x-dynamic-component :component="\App\Enums\LessonType::tryFrom($item['type'])?->icon() ?? 'heroicon-o-play-circle'" class="size-4" aria-hidden="true" />
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-semibold">{{ $item['title'] }}</span>
                                                        <span class="flex items-center gap-1.5 text-[0.7rem] text-muted">
                                                            <span>{{ __('course.types.'.$item['type']) }}</span>
                                                            @if ($item['duration_seconds'] > 0)
                                                                <span aria-hidden="true">·</span>
                                                                <span>{{ course_duration($item['duration_seconds']) }}</span>
                                                            @endif
                                                            @if ($item['is_free_preview'])
                                                                <span class="rounded-full bg-emerald-500/15 px-1.5 py-0.5 font-bold text-emerald-700 dark:text-emerald-300">{{ __('course.player.preview') }}</span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                </a>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="px-3 py-2 text-xs text-muted">{{ __('course.curriculum.empty') }}</li>
                                    @endforelse
                                </ol>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            {{-- Lesson --}}
            <div class="min-w-0">
                <button
                    type="button"
                    @click="sidebarOpen = ! sidebarOpen"
                    class="btn btn-ghost mb-4 w-full lg:hidden"
                    :aria-expanded="sidebarOpen ? 'true' : 'false'"
                >
                    <x-heroicon-o-list-bullet class="size-5" aria-hidden="true" />
                    {{ __('course.player.content') }}
                </button>

                <div class="glass overflow-hidden rounded-3xl">
                    <div class="border-b border-black/5 p-5 dark:border-white/10 sm:p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-primary-ink">
                                <x-dynamic-component :component="$type->icon()" class="size-3.5" aria-hidden="true" />
                                {{ __('course.types.'.$type->value) }}
                            </span>
                            @if ($lesson->is_free_preview)
                                <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">{{ __('course.player.preview') }}</span>
                            @endif
                            @if ($lesson->duration_seconds > 0)
                                <span class="text-xs font-semibold text-muted">{{ course_duration($lesson->duration_seconds) }}</span>
                            @endif
                        </div>
                        <h2 class="mt-3 text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $lesson->title }}</h2>
                    </div>

                    @if ($video !== null)
                        <div class="bg-black">
                            <div class="aspect-video w-full">
                                @if ($video['video_type'] === 'embed')
                                    <iframe
                                        src="{{ $video['url'] }}"
                                        class="size-full"
                                        title="{{ $lesson->title }}"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                        referrerpolicy="strict-origin-when-cross-origin"
                                    ></iframe>
                                @else
                                    <video id="lesson-video" class="size-full" controls playsinline preload="metadata" src="{{ $video['url'] }}">
                                        {{ __('course.player.video_unsupported') }}
                                    </video>
                                @endif
                            </div>
                        </div>
                    @elseif ($type->hasPlayableVideo())
                        <div class="grid aspect-video w-full place-items-center bg-black/90 p-6 text-center text-sm font-semibold text-white/80">
                            {{ __('course.player.no_video') }}
                        </div>
                    @endif

                    <div class="space-y-6 p-5 sm:p-6">
                        @if (! empty($courseComplete))
                            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                                {{ __('course.completion.complete') }}
                            </div>
                        @endif

                        @if ($quiz)
                            <a href="{{ route('courses.quizzes.show', ['course' => $course->code, 'quiz' => $quiz]) }}" class="btn btn-primary">
                                {{ __('course.quiz.open') }}
                            </a>
                        @endif

                        @if ($assignment)
                            <a href="{{ route('courses.assignments.show', ['course' => $course->code, 'assignment' => $assignment]) }}" class="btn btn-primary">
                                {{ __('course.assignment.open') }}
                            </a>
                        @endif

                        @if (filled($lesson->content))
                            <section aria-labelledby="lesson-notes">
                                <h3 id="lesson-notes" class="text-sm font-extrabold uppercase tracking-wider text-muted">{{ __('course.player.notes') }}</h3>
                                <div class="prose-site mt-3 max-w-none text-sm sm:text-base">{!! nl2br(e($lesson->content)) !!}</div>
                            </section>
                        @elseif (! $type->hasPlayableVideo() && ! $quiz && ! $assignment)
                            <p class="text-sm text-muted">{{ __('course.player.no_content') }}</p>
                        @endif

                        @if ($resources->isNotEmpty())
                            <section aria-labelledby="lesson-resources">
                                <h3 id="lesson-resources" class="text-sm font-extrabold uppercase tracking-wider text-muted">{{ __('course.player.resources') }}</h3>
                                <ul class="mt-3 grid gap-2 sm:grid-cols-2" role="list">
                                    @foreach ($resources as $resource)
                                        <li>
                                            <a
                                                href="{{ route('lessons.resources.download', ['lesson' => $lesson, 'resource' => $resource]) }}"
                                                class="flex items-center gap-3 rounded-2xl border border-black/5 bg-black/[0.02] px-4 py-3 transition hover:border-primary/40 hover:bg-primary/5 dark:border-white/10 dark:bg-white/[0.03]"
                                            >
                                                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary-ink">
                                                    <x-heroicon-o-arrow-down-tray class="size-4" aria-hidden="true" />
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-semibold">{{ $resource->title }}</span>
                                                    <span class="block text-xs text-muted">{{ __('course.player.download') }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    </div>
                </div>

                {{-- Previous / next --}}
                <nav class="mt-5 grid gap-3 sm:grid-cols-2" aria-label="{{ __('course.player.content') }}">
                    @if ($previous !== null)
                        @if ($locked((int) $previous['id']))
                            <span class="flex items-center gap-3 rounded-2xl border border-black/5 px-4 py-3 opacity-60 dark:border-white/10">
                                <x-heroicon-o-lock-closed class="size-4 shrink-0 text-muted" aria-hidden="true" />
                                <span class="min-w-0 text-sm font-semibold text-muted">{{ __('course.player.previous') }}</span>
                            </span>
                        @else
                            <a href="{{ route('courses.learn', ['course' => $course->code, 'lesson' => $previous['id']]) }}" class="group flex items-center gap-3 rounded-2xl border border-black/5 px-4 py-3 transition hover:border-primary/40 hover:bg-primary/5 dark:border-white/10">
                                <x-heroicon-o-arrow-left class="size-4 shrink-0 text-muted transition group-hover:-translate-x-0.5" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-muted">{{ __('course.player.previous') }}</span>
                                    <span class="mt-0.5 block truncate text-sm font-semibold">{{ $previous['title'] }}</span>
                                </span>
                            </a>
                        @endif
                    @endif

                    @if ($next !== null)
                        @if ($locked((int) $next['id']))
                            <span class="flex items-center justify-end gap-3 rounded-2xl border border-black/5 px-4 py-3 text-right opacity-60 dark:border-white/10 sm:col-start-2">
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-muted">{{ __('course.player.next') }}</span>
                                    <span class="mt-0.5 block truncate text-sm font-semibold">{{ __('course.player.locked', ['days' => site_number($next['drip_after_days'])]) }}</span>
                                </span>
                                <x-heroicon-o-lock-closed class="size-4 shrink-0 text-muted" aria-hidden="true" />
                            </span>
                        @else
                            <a href="{{ route('courses.learn', ['course' => $course->code, 'lesson' => $next['id']]) }}" class="group flex items-center justify-end gap-3 rounded-2xl border border-black/5 px-4 py-3 text-right transition hover:border-primary/40 hover:bg-primary/5 dark:border-white/10 sm:col-start-2">
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-muted">{{ __('course.player.next') }}</span>
                                    <span class="mt-0.5 block truncate text-sm font-semibold">{{ $next['title'] }}</span>
                                </span>
                                <x-heroicon-o-arrow-right class="size-4 shrink-0 text-muted transition group-hover:translate-x-0.5" aria-hidden="true" />
                            </a>
                        @endif
                    @endif
                </nav>
            </div>
        </div>
    </div>
</x-layouts.site>
