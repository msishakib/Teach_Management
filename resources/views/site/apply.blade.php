{{-- Simple multi-step application form using Alpine.js --}}
<x-layouts.site :title="__('site.apply.title')" :description="__('site.apply.subtitle')">
    <x-section id="apply" :eyebrow="__('site.apply.eyebrow')" :title="__('site.apply.title')" :subtitle="__('site.apply.subtitle')">
        <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[0.85fr_1.15fr]">
            <aside data-reveal class="grad-bg relative overflow-hidden rounded-3xl p-8 text-white shadow-lift">
                <div class="absolute -right-16 -top-16 size-60 rounded-full bg-white/15" aria-hidden="true"></div>
                <h2 class="relative text-2xl font-extrabold tracking-tight">{{ __('site.apply.steps_title') }}</h2>
                <ol class="relative mt-6 space-y-5">
                    @foreach (['one', 'two', 'three'] as $n => $step)
                        <li class="flex gap-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white/20 font-extrabold">{{ site_number($n + 1) }}</span>
                            <div>
                                <p class="font-bold">{{ __('site.apply.steps.'.$step.'.title') }}</p>
                                <p class="text-sm text-white/85">{{ __('site.apply.steps.'.$step.'.text') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </aside>

            <form method="POST" action="{{ route('apply.store', ['locale' => app()->getLocale()]) }}" data-reveal style="--d: 100ms" class="glass-strong space-y-5 rounded-3xl p-6 shadow-soft sm:p-8" novalidate enctype="multipart/form-data">
                @csrf

                @if (session('status'))
                    <div role="status" class="flex items-start gap-3 rounded-2xl bg-emerald-500/12 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="size-5 shrink-0" aria-hidden="true" /> {{ session('status') }}
                    </div>
                @endif

                <div class="sr-only" aria-hidden="true">
                    <label>Website <input type="text" name="hp_name" tabindex="-1" autocomplete="off"></label>
                </div>

                <div>
                    <label for="apply-name" class="mb-1.5 block text-sm font-semibold">{{ __('site.apply.name') }}</label>
                    <input id="apply-name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="field" @error('name') aria-invalid="true" aria-describedby="apply-name-error" @enderror>
                    @error('name') <p id="apply-name-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="apply-phone" class="mb-1.5 block text-sm font-semibold">{{ __('site.apply.phone') }}</label>
                        <input id="apply-phone" name="phone" type="tel" required autocomplete="tel" value="{{ old('phone') }}" class="field" @error('phone') aria-invalid="true" aria-describedby="apply-phone-error" @enderror>
                        @error('phone') <p id="apply-phone-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="apply-email" class="mb-1.5 block text-sm font-semibold">{{ __('site.apply.email') }}</label>
                        <input id="apply-email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" class="field" @error('email') aria-invalid="true" aria-describedby="apply-email-error" @enderror>
                        @error('email') <p id="apply-email-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="apply-course" class="mb-1.5 block text-sm font-semibold">{{ __('site.apply.course') }}</label>
                    <select id="apply-course" name="course_id" required class="field" @error('course_id') aria-invalid="true" aria-describedby="apply-course-error" @enderror>
                        <option value="">{{ __('site.apply.choose_course') }}</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((int) old('course_id', $selectedCourse) === $course->id)>{{ $course->name }}</option>
                        @endforeach
                    </select>
                    @error('course_id') <p id="apply-course-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="apply-message" class="mb-1.5 block text-sm font-semibold">{{ __('site.apply.message') }}</label>
                    <textarea id="apply-message" name="message" rows="4" class="field resize-y">{{ old('message') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary w-full !py-3.5 text-base">
                    {{ __('site.apply.submit') }} <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                </button>
                <p class="text-center text-xs text-muted">{{ __('site.apply.privacy') }}</p>
            </form>
        </div>
    </x-section>
</x-layouts.site>
