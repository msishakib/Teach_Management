<x-layouts.site :title="__('site.register.success_title')" :description="__('site.register.success_text')" :noindex="true">
    <x-section id="register-success" class="!py-20 sm:!py-28">
        <div class="mx-auto max-w-xl text-center" data-reveal>
            <span class="grad-bg mx-auto grid size-16 place-items-center rounded-2xl text-white shadow-lift">
                <x-heroicon-o-check class="size-8" aria-hidden="true" />
            </span>

            <h1 class="mt-7 text-balance text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('site.register.success_title') }}</h1>
            <p class="mx-auto mt-4 max-w-lg text-pretty text-base text-muted sm:text-lg">{{ __('site.register.success_text') }}</p>

            <dl class="glass-strong mx-auto mt-9 max-w-md space-y-4 rounded-3xl p-6 text-left shadow-soft sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm font-semibold text-muted">{{ __('site.register.application_no') }}</dt>
                    <dd class="rounded-lg bg-primary/10 px-3 py-1.5 font-mono text-sm font-bold text-primary-ink">{{ $registration->registration_no }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm font-semibold text-muted">{{ __('site.register.status') }}</dt>
                    <dd class="rounded-full bg-amber-500/15 px-3 py-1.5 text-sm font-bold text-amber-700 dark:text-amber-300">
                        {{ __('site.register.statuses.'.$registration->status) }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm font-semibold text-muted">{{ __('site.register.applicant') }}</dt>
                    <dd class="font-semibold">{{ $registration->name }}</dd>
                </div>
            </dl>

            <p class="mx-auto mt-7 max-w-lg rounded-2xl bg-primary/5 p-4 text-sm text-muted">{{ __('site.register.review_note') }}</p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('admissions.track.form', ['type' => 'registration', 'number' => $registration->registration_no]) }}" class="btn btn-primary">
                    <x-heroicon-o-magnifying-glass class="size-4" aria-hidden="true" />
                    {{ __('site.track.short') }}
                </a>
                <a href="{{ route('home') }}" class="btn btn-ghost">
                    <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
                    {{ __('site.register.back_home') }}
                </a>
            </div>
        </div>
    </x-section>
</x-layouts.site>
