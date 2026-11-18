@php
    $record = $registration ?? $application;
    $isRegistration = $registration !== null;
    $status = $record?->status ?? 'pending';

    $statusLabels = [
        'pending' => __('site.track.statuses.pending'),
        'reviewing' => __('site.track.statuses.reviewing'),
        'approved' => __('site.track.statuses.approved'),
        'rejected' => __('site.track.statuses.rejected'),
    ];
    $statusClasses = [
        'pending' => 'bg-amber-500/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
        'reviewing' => 'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300',
        'approved' => 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
        'rejected' => 'bg-red-500/15 text-red-700 ring-red-500/30 dark:text-red-300',
    ];
    $statusIcons = [
        'pending' => 'clock',
        'reviewing' => 'magnifying-glass',
        'approved' => 'check-circle',
        'rejected' => 'x-circle',
    ];

    $reviewStarted = in_array($status, ['reviewing', 'approved', 'rejected'], true);
    $decisionMade = in_array($status, ['approved', 'rejected'], true);

    $timeline = [
        [
            'title' => __('site.track.timeline.submitted'),
            'text' => __('site.track.timeline.submitted_text'),
            'state' => 'done',
        ],
        [
            'title' => __('site.track.timeline.review'),
            'text' => __('site.track.timeline.review_text'),
            'state' => $reviewStarted ? 'done' : ($status === 'pending' ? 'current' : 'todo'),
        ],
        [
            'title' => __('site.track.timeline.decision'),
            'text' => __('site.track.timeline.decision_text'),
            'state' => $decisionMade
                ? ($status === 'rejected' ? 'rejected' : 'done')
                : ($status === 'reviewing' ? 'current' : 'todo'),
        ],
    ];

    $stepClasses = [
        'done' => 'bg-primary text-white',
        'current' => 'bg-primary/15 text-primary-ink ring-2 ring-primary/40',
        'rejected' => 'bg-red-500/15 text-red-600 ring-2 ring-red-500/30 dark:text-red-300',
        'todo' => 'bg-surface-solid text-muted ring-1 ring-line',
    ];

    $number = $record?->registration_no ?? $record?->application_no ?? $number ?? null;
@endphp

<x-layouts.site :title="__('site.track.title')" :description="__('site.track.subtitle')" :noindex="true">
    <x-section id="track" :eyebrow="__('site.track.eyebrow')" :title="__('site.track.title')" :subtitle="__('site.track.subtitle')">
        <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[0.8fr_1.2fr] lg:gap-8">
            {{-- Help card --}}
            <aside data-reveal class="grad-bg relative order-2 overflow-hidden rounded-3xl p-7 text-white shadow-lift sm:p-9 lg:order-1">
                <div class="absolute -right-16 -top-16 size-60 rounded-full bg-white/15" aria-hidden="true"></div>
                <div class="absolute -bottom-20 -left-12 size-56 rounded-full bg-white/10" aria-hidden="true"></div>

                <h2 class="relative text-2xl font-extrabold tracking-tight">{{ __('site.track.how_title') }}</h2>

                <ol class="relative mt-7 space-y-6">
                    @foreach (['one', 'two', 'three'] as $index => $step)
                        <li class="flex gap-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white/20 font-extrabold">{{ site_number($index + 1) }}</span>
                            <div>
                                <p class="font-bold">{{ __('site.track.steps.'.$step.'.title') }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-white/85">{{ __('site.track.steps.'.$step.'.text') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="relative mt-8 flex gap-3 rounded-2xl bg-white/10 p-4 text-sm leading-relaxed text-white/90">
                    <x-heroicon-o-lock-closed class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <p>{{ __('site.track.privacy') }}</p>
                </div>

                <a href="{{ route('register') }}" class="btn mt-5 w-full !border !border-white/40 !py-3 text-white hover:bg-white/15">
                    <x-heroicon-o-user-plus class="size-5" aria-hidden="true" />
                    {{ __('site.nav.register') }}
                </a>
            </aside>

            {{-- Form or result --}}
            <div class="order-1 space-y-5 lg:order-2">
                @if ($searched)
                    @if ($record)
                        <div data-reveal class="glass-strong overflow-hidden rounded-3xl shadow-soft">
                            <div class="border-b border-line/70 bg-primary/5 p-6 sm:p-8">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-muted">{{ $isRegistration ? __('site.track.type_registration') : __('site.track.type_admission') }}</p>
                                        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">{{ $record->name }}</h2>
                                        <p class="mt-1 font-mono text-sm text-muted">{{ $number }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-bold ring-1 {{ $statusClasses[$status] ?? $statusClasses['pending'] }}">
                                        <x-dynamic-component :component="'heroicon-o-'.($statusIcons[$status] ?? 'clock')" class="size-4" aria-hidden="true" />
                                        {{ $statusLabels[$status] ?? ucfirst($status) }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-6 p-6 sm:p-8">
                                {{-- Status message --}}
                                <div class="flex gap-3 rounded-2xl bg-primary/5 p-4 text-sm leading-relaxed">
                                    <x-heroicon-o-information-circle class="mt-0.5 size-5 shrink-0 text-primary-ink" aria-hidden="true" />
                                    <p>{{ __('site.track.messages.'.($isRegistration ? 'registration' : 'admission').'.'.$status) }}</p>
                                </div>

                                {{-- Details --}}
                                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="font-semibold text-muted">{{ __('site.track.number') }}</dt>
                                        <dd class="mt-1 font-mono font-bold">{{ $number }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-muted">{{ __('site.track.applicant') }}</dt>
                                        <dd class="mt-1 font-semibold">{{ $record->name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-muted">{{ __('site.track.submitted') }}</dt>
                                        <dd class="mt-1">{{ site_date($record->created_at) }}</dd>
                                    </div>

                                    @if ($isRegistration)
                                        <div>
                                            <dt class="font-semibold text-muted">{{ __('site.track.last_exam') }}</dt>
                                            <dd class="mt-1">{{ $record->last_exam ?: __('site.track.not_available') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold text-muted">{{ __('site.track.last_institution') }}</dt>
                                            <dd class="mt-1">{{ $record->last_institution ?: __('site.track.not_available') }}</dd>
                                        </div>
                                    @else
                                        <div>
                                            <dt class="font-semibold text-muted">{{ __('site.track.course') }}</dt>
                                            <dd class="mt-1">{{ $application->course?->name ?? __('site.track.not_available') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-semibold text-muted">{{ __('site.track.batch') }}</dt>
                                            <dd class="mt-1">{{ $application->batch?->name ?? __('site.track.not_available') }}</dd>
                                        </div>
                                    @endif

                                    @if ($record->reviewed_at)
                                        <div>
                                            <dt class="font-semibold text-muted">{{ __('site.track.reviewed_at') }}</dt>
                                            <dd class="mt-1">{{ site_date($record->reviewed_at) }}</dd>
                                        </div>
                                    @endif
                                </dl>

                                {{-- Timeline --}}
                                <div class="border-t border-line/70 pt-6">
                                    <h3 class="mb-5 text-sm font-bold uppercase tracking-wider text-muted">{{ __('site.track.progress') }}</h3>
                                    <ol class="space-y-0" role="list">
                                        @foreach ($timeline as $step)
                                            <li class="relative flex gap-4 pb-7 last:pb-0">
                                                @if (! $loop->last)
                                                    <span class="absolute left-4 top-9 h-[calc(100%-1.25rem)] w-px bg-line" aria-hidden="true"></span>
                                                @endif
                                                <span class="relative grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold {{ $stepClasses[$step['state']] }}">
                                                    @if ($step['state'] === 'done')
                                                        <x-heroicon-o-check class="size-4" aria-hidden="true" />
                                                    @elseif ($step['state'] === 'rejected')
                                                        <x-heroicon-o-x-mark class="size-4" aria-hidden="true" />
                                                    @else
                                                        {{ site_number($loop->iteration) }}
                                                    @endif
                                                </span>
                                                <div class="min-w-0 pt-0.5">
                                                    <p class="font-bold">{{ $step['title'] }}</p>
                                                    <p class="mt-0.5 text-sm text-muted">{{ $step['text'] }}</p>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>

                                {{-- Remarks --}}
                                @if (filled($record->remarks))
                                    <div class="rounded-2xl bg-amber-500/10 p-4">
                                        <p class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">{{ __('site.track.remarks') }}</p>
                                        <p class="mt-1.5 text-sm leading-relaxed">{{ $record->remarks }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div data-reveal role="alert" class="glass-strong flex gap-4 rounded-3xl border border-amber-500/30 p-6 shadow-soft sm:p-8">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-300">
                                <x-heroicon-o-exclamation-triangle class="size-6" aria-hidden="true" />
                            </span>
                            <div>
                                <p class="text-lg font-extrabold">{{ __('site.track.not_found_title') }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-muted">{{ __('site.track.not_found_text') }}</p>
                                @if (filled($number))
                                    <p class="mt-3 font-mono text-sm font-semibold">{{ $number }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <a href="{{ route('admissions.track.form') }}" class="btn btn-ghost w-full !py-3">
                        <x-heroicon-o-arrow-path class="size-5" aria-hidden="true" />
                        {{ __('site.track.track_another') }}
                    </a>
                @else
                    <form method="POST" action="{{ route('admissions.track') }}" data-reveal
                          class="glass-strong space-y-5 rounded-3xl p-6 shadow-soft sm:p-8" novalidate>
                        @csrf

                        <div>
                            <label for="track-type" class="mb-1.5 block text-sm font-semibold">{{ __('site.track.type') }}</label>
                            <select id="track-type" name="type" class="field" @error('type') aria-invalid="true" aria-describedby="track-type-error" @enderror>
                                <option value="admission" @selected(old('type', $type) === 'admission')>{{ __('site.track.type_admission') }}</option>
                                <option value="registration" @selected(old('type', $type) === 'registration')>{{ __('site.track.type_registration') }}</option>
                            </select>
                            @error('type') <p id="track-type-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="track-number" class="mb-1.5 block text-sm font-semibold">{{ __('site.track.number') }}</label>
                            <input id="track-number" name="application_no" type="text" required autocomplete="off"
                                   value="{{ old('application_no', $number) }}" placeholder="{{ __('site.track.number_placeholder') }}"
                                   class="field font-mono" @error('application_no') aria-invalid="true" aria-describedby="track-number-error" @enderror>
                            @error('application_no') <p id="track-number-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="track-phone" class="mb-1.5 block text-sm font-semibold">{{ __('site.track.phone') }}</label>
                            <input id="track-phone" name="phone" type="tel" required autocomplete="tel"
                                   value="{{ old('phone') }}" placeholder="{{ __('site.track.phone_placeholder') }}"
                                   class="field" @error('phone') aria-invalid="true" aria-describedby="track-phone-error" @enderror>
                            @error('phone') <p id="track-phone-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-full !py-3.5 text-base">
                            {{ __('site.track.submit') }} <x-heroicon-o-magnifying-glass class="size-5" aria-hidden="true" />
                        </button>

                        <p class="text-center text-xs text-muted">{{ __('site.track.hint') }}</p>
                    </form>
                @endif
            </div>
        </div>
    </x-section>
</x-layouts.site>
