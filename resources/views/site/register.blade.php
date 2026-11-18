<x-layouts.site :title="__('site.register.title')" :description="__('site.register.subtitle')">
    <x-section id="register" :eyebrow="__('site.register.eyebrow')" :title="__('site.register.title')" :subtitle="__('site.register.subtitle')">
        <div class="mx-auto max-w-4xl">
            <form method="POST" action="{{ route('register.store') }}" data-reveal
                  class="glass-strong space-y-8 rounded-3xl p-6 shadow-soft sm:p-9" novalidate>
                @csrf

                {{-- Honeypot --}}
                <div class="sr-only" aria-hidden="true">
                    <label>Website <input type="text" name="hp_name" tabindex="-1" autocomplete="off"></label>
                </div>

                @if ($errors->any())
                    <div role="alert" class="rounded-2xl bg-red-500/10 p-4 text-sm font-semibold text-red-700 dark:text-red-300">
                        <p class="flex items-center gap-2">
                            <x-heroicon-o-exclamation-triangle class="size-5 shrink-0" aria-hidden="true" />
                            {{ __('site.register.fix_errors') }}
                        </p>
                    </div>
                @endif

                {{-- Personal & identity --}}
                <fieldset class="space-y-5">
                    <legend class="mb-4 flex items-center gap-2 text-lg font-extrabold tracking-tight">
                        <x-heroicon-o-identification class="size-5 text-primary-ink" aria-hidden="true" />
                        {{ __('site.register.personal') }}
                    </legend>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="register-name" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.name') }}</label>
                            <input id="register-name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="field" @error('name') aria-invalid="true" aria-describedby="register-name-error" @enderror>
                            @error('name') <p id="register-name-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="register-dob" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.dob') }}</label>
                            <input id="register-dob" name="dob" type="date" required max="{{ now()->subDay()->toDateString() }}" value="{{ old('dob') }}" class="field" @error('dob') aria-invalid="true" aria-describedby="register-dob-error" @enderror>
                            @error('dob') <p id="register-dob-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="register-gender" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.gender') }}</label>
                            <select id="register-gender" name="gender" required class="field" @error('gender') aria-invalid="true" aria-describedby="register-gender-error" @enderror>
                                <option value="">{{ __('site.register.choose') }}</option>
                                @foreach (['male', 'female', 'other'] as $gender)
                                    <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ __('site.register.genders.'.$gender) }}</option>
                                @endforeach
                            </select>
                            @error('gender') <p id="register-gender-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="register-phone" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.phone') }}</label>
                            <input id="register-phone" name="phone" type="tel" required autocomplete="tel" value="{{ old('phone') }}" class="field" @error('phone') aria-invalid="true" aria-describedby="register-phone-error" @enderror>
                            @error('phone') <p id="register-phone-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="register-email" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.email') }}</label>
                            <input id="register-email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" class="field" aria-describedby="register-email-help @error('email') register-email-error @enderror" @error('email') aria-invalid="true" @enderror>
                            <p id="register-email-help" class="mt-1.5 text-xs text-muted">{{ __('site.register.email_help') }}</p>
                            @error('email') <p id="register-email-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="register-national-id" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.national_id') }}</label>
                            <input id="register-national-id" name="national_id" type="text" value="{{ old('national_id') }}" class="field">
                        </div>

                        <div>
                            <label for="register-birth-certificate" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.birth_certificate_no') }}</label>
                            <input id="register-birth-certificate" name="birth_certificate_no" type="text" value="{{ old('birth_certificate_no') }}" class="field">
                        </div>

                        <div>
                            <label for="register-religion" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.religion') }}</label>
                            <input id="register-religion" name="religion" type="text" value="{{ old('religion') }}" class="field">
                        </div>

                        <div>
                            <label for="register-blood-group" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.blood_group') }}</label>
                            <select id="register-blood-group" name="blood_group" class="field">
                                <option value="">{{ __('site.register.choose') }}</option>
                                @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group)
                                    <option value="{{ $group }}" @selected(old('blood_group') === $group)>{{ $group }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </fieldset>

                {{-- Address --}}
                <fieldset class="space-y-5 border-t border-line/70 pt-8">
                    <legend class="mb-4 flex items-center gap-2 text-lg font-extrabold tracking-tight">
                        <x-heroicon-o-map-pin class="size-5 text-primary-ink" aria-hidden="true" />
                        {{ __('site.register.address') }}
                    </legend>

                    <div>
                        <label for="register-present-address" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.present_address') }}</label>
                        <textarea id="register-present-address" name="present_address" rows="3" required class="field resize-y" @error('present_address') aria-invalid="true" aria-describedby="register-present-address-error" @enderror>{{ old('present_address') }}</textarea>
                        @error('present_address') <p id="register-present-address-error" class="mt-1.5 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="register-permanent-address" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.permanent_address') }}</label>
                        <textarea id="register-permanent-address" name="permanent_address" rows="3" class="field resize-y" aria-describedby="register-permanent-address-help">{{ old('permanent_address') }}</textarea>
                        <p id="register-permanent-address-help" class="mt-1.5 text-xs text-muted">{{ __('site.register.permanent_help') }}</p>
                    </div>
                </fieldset>

                {{-- Academic background --}}
                <fieldset class="space-y-5 border-t border-line/70 pt-8">
                    <legend class="mb-4 flex items-center gap-2 text-lg font-extrabold tracking-tight">
                        <x-heroicon-o-academic-cap class="size-5 text-primary-ink" aria-hidden="true" />
                        {{ __('site.register.academic') }}
                    </legend>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="register-last-institution" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.last_institution') }}</label>
                            <input id="register-last-institution" name="last_institution" type="text" value="{{ old('last_institution') }}" class="field">
                        </div>

                        <div>
                            <label for="register-last-exam" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.last_exam') }}</label>
                            <input id="register-last-exam" name="last_exam" type="text" value="{{ old('last_exam') }}" class="field" placeholder="{{ __('site.register.last_exam_placeholder') }}">
                        </div>

                        <div>
                            <label for="register-last-result" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.last_result') }}</label>
                            <input id="register-last-result" name="last_result" type="text" value="{{ old('last_result') }}" class="field" placeholder="{{ __('site.register.last_result_placeholder') }}">
                        </div>

                        <div>
                            <label for="register-passing-year" class="mb-1.5 block text-sm font-semibold">{{ __('site.register.passing_year') }}</label>
                            <input id="register-passing-year" name="passing_year" type="number" inputmode="numeric" min="1950" max="{{ now()->year }}" value="{{ old('passing_year') }}" class="field">
                        </div>
                    </div>
                </fieldset>

                <div class="border-t border-line/70 pt-8">
                    <button type="submit" class="btn btn-primary w-full !py-3.5 text-base">
                        {{ __('site.register.submit') }} <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                    </button>
                    <p class="mt-3 text-center text-xs text-muted">{{ __('site.register.privacy') }}</p>
                    <p class="mt-4 text-center text-sm text-muted">
                        {{ __('site.register.already') }}
                        <a href="{{ route('admissions.track.form') }}" class="font-semibold text-primary-ink hover:underline">{{ __('site.register.track') }}</a>
                    </p>
                </div>
            </form>
        </div>
    </x-section>
</x-layouts.site>
