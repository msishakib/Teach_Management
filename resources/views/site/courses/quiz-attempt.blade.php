@php
    $isClosed = $attempt->status->isClosed();
    $onePerPage = (bool) ($payload['quiz']['one_question_per_page'] ?? false);
@endphp

<x-layouts.site :title="$quiz->title" :noindex="true">
    <div
        class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8"
        x-data="quizAttempt(@js([
            'payload' => $payload,
            'onePerPage' => $onePerPage,
            'isClosed' => $isClosed,
            'saveUrlTemplate' => route('courses.quizzes.attempts.save', ['course' => $course->code, 'quiz' => $quiz, 'attempt' => $attempt, 'question' => '__Q__']),
            'submitUrl' => route('courses.quizzes.attempts.submit', ['course' => $course->code, 'quiz' => $quiz, 'attempt' => $attempt]),
            'csrf' => csrf_token(),
            'autosavedLabel' => __('course.quiz.autosaved'),
        ]))"
    >
        <a href="{{ route('courses.quizzes.show', ['course' => $course->code, 'quiz' => $quiz]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted transition hover:text-primary-ink">
            <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
            {{ $quiz->title }}
        </a>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-extrabold tracking-tight">{{ $quiz->title }}</h1>
            <div class="text-sm font-semibold text-muted" x-show="deadlineAt" x-cloak>
                <span x-text="remainingLabel"></span>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm font-semibold text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        <p class="mt-2 text-xs font-semibold text-muted" x-text="statusMessage" x-show="statusMessage" x-cloak></p>

        @if ($isClosed)
            <div class="mt-4 rounded-2xl border border-black/5 p-4 text-sm font-semibold dark:border-white/10">
                {{ __('course.quiz.score', ['score' => site_number($payload['attempt']['score']), 'percent' => site_number($payload['attempt']['percent'])]) }}
                —
                {{ $payload['attempt']['passed'] ? __('course.quiz.passed') : __('course.quiz.failed') }}
            </div>
        @endif

        <template x-for="(question, index) in visibleQuestions()" :key="question.id">
            <section class="mt-6 rounded-3xl border border-black/5 p-5 dark:border-white/10">
                <p class="text-xs font-bold uppercase tracking-wider text-muted"
                   x-text="'{{ __('course.quiz.question', ['number' => '']) }}'.replace(/\s+$/, '') + ' ' + (index + 1 + pageOffset())"></p>
                <h2 class="mt-2 text-lg font-bold" x-text="question.text"></h2>
                <p class="mt-1 text-xs text-muted" x-text="question.marks + ' marks'"></p>

                <div class="mt-4 space-y-2" x-show="question.type !== 'short_answer'">
                    <template x-for="option in question.options" :key="option.id">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-black/5 px-4 py-3 dark:border-white/10"
                               :class="isSelected(question, option.id) ? 'border-primary/40 bg-primary/5' : ''">
                            <input
                                :type="question.type === 'multiple_choice' ? 'checkbox' : 'radio'"
                                :name="'q_'+question.id"
                                :value="option.id"
                                :disabled="isClosed"
                                :checked="isSelected(question, option.id)"
                                @change="toggleOption(question, option.id, $event)"
                                class="mt-1"
                            >
                            <span class="text-sm font-medium" x-text="option.text"></span>
                            <span x-show="payload.answers_revealed && option.is_correct" class="ml-auto text-xs font-bold text-emerald-700">✓</span>
                        </label>
                    </template>
                </div>

                <div class="mt-4" x-show="question.type === 'short_answer'">
                    <textarea
                        class="w-full rounded-2xl border border-black/10 bg-transparent p-3 text-sm"
                        rows="4"
                        :disabled="isClosed"
                        x-model="question.answer.answer_text"
                        @input.debounce.600ms="saveAnswer(question)"
                    ></textarea>
                </div>

                <div class="mt-4 rounded-2xl bg-black/[0.03] p-3 text-sm dark:bg-white/[0.04]" x-show="payload.answers_revealed && question.explanation" x-cloak>
                    <p class="text-xs font-bold uppercase tracking-wider text-muted">{{ __('course.quiz.explanation') }}</p>
                    <p class="mt-1" x-text="question.explanation"></p>
                </div>
            </section>
        </template>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3" x-show="onePerPage && !isClosed">
            <button type="button" class="btn btn-ghost" @click="prevPage()" :disabled="page === 0">{{ __('course.quiz.previous') }}</button>
            <span class="text-sm text-muted" x-text="(page + 1) + ' / ' + payload.questions.length"></span>
            <button type="button" class="btn btn-ghost" @click="nextPage()" :disabled="page >= payload.questions.length - 1">{{ __('course.quiz.next') }}</button>
        </div>

        @unless ($isClosed)
            <form class="mt-8" method="post" :action="submitUrl" @submit="submitting = true">
                @csrf
                <button type="submit" class="btn btn-primary" :disabled="submitting">{{ __('course.quiz.submit') }}</button>
            </form>
        @endunless
    </div>

    @push('scripts')
        <script>
            function quizAttempt(config) {
                return {
                    payload: config.payload,
                    onePerPage: config.onePerPage,
                    isClosed: config.isClosed,
                    saveUrlTemplate: config.saveUrlTemplate,
                    submitUrl: config.submitUrl,
                    csrf: config.csrf,
                    autosavedLabel: config.autosavedLabel,
                    page: 0,
                    statusMessage: '',
                    submitting: false,
                    deadlineAt: config.payload.attempt.deadline_at ? new Date(config.payload.attempt.deadline_at) : null,
                    remainingLabel: '',
                    init() {
                        if (this.deadlineAt && !this.isClosed) {
                            this.tick();
                            setInterval(() => this.tick(), 1000);
                        }
                    },
                    tick() {
                        const ms = this.deadlineAt - new Date();
                        if (ms <= 0) {
                            this.remainingLabel = '0:00';
                            if (!this.submitting) {
                                this.submitting = true;
                                window.location.href = this.submitUrl;
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = this.submitUrl;
                                const token = document.createElement('input');
                                token.type = 'hidden';
                                token.name = '_token';
                                token.value = this.csrf;
                                form.appendChild(token);
                                document.body.appendChild(form);
                                form.submit();
                            }
                            return;
                        }
                        const total = Math.floor(ms / 1000);
                        const m = Math.floor(total / 60);
                        const s = String(total % 60).padStart(2, '0');
                        this.remainingLabel = m + ':' + s;
                    },
                    visibleQuestions() {
                        if (!this.onePerPage || this.isClosed) {
                            return this.payload.questions;
                        }
                        return [this.payload.questions[this.page]].filter(Boolean);
                    },
                    pageOffset() {
                        return this.onePerPage && !this.isClosed ? this.page : 0;
                    },
                    isSelected(question, optionId) {
                        return (question.answer.selected_option_ids || []).map(Number).includes(Number(optionId));
                    },
                    toggleOption(question, optionId, event) {
                        if (this.isClosed) return;
                        let selected = (question.answer.selected_option_ids || []).map(Number);
                        if (question.type === 'multiple_choice') {
                            if (event.target.checked) {
                                selected.push(Number(optionId));
                            } else {
                                selected = selected.filter((id) => id !== Number(optionId));
                            }
                        } else {
                            selected = [Number(optionId)];
                        }
                        question.answer.selected_option_ids = [...new Set(selected)];
                        this.saveAnswer(question);
                    },
                    async saveAnswer(question) {
                        if (this.isClosed) return;
                        const url = this.saveUrlTemplate.replace('__Q__', question.id);
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify({
                                selected_option_ids: question.answer.selected_option_ids || [],
                                answer_text: question.answer.answer_text || null,
                            }),
                        });
                        if (response.ok) {
                            this.statusMessage = this.autosavedLabel;
                        } else if (response.status === 422) {
                            const data = await response.json();
                            this.statusMessage = Object.values(data.errors || {}).flat().join(' ') || 'Unable to save';
                            if (String(this.statusMessage).includes('Time is up')) {
                                window.location.reload();
                            }
                        }
                    },
                    nextPage() {
                        if (this.page < this.payload.questions.length - 1) this.page++;
                    },
                    prevPage() {
                        if (this.page > 0) this.page--;
                    },
                };
            }
        </script>
    @endpush
</x-layouts.site>
