<x-filament-panels::page>
    @if ($enabled)
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-lg font-semibold">Two-factor authentication is enabled</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                You will be asked for a verification code after entering your password.
            </p>

            <div class="mt-6">
                <h3 class="text-sm font-semibold">Recovery codes</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Each code can be used once if you lose access to your authenticator app.
                </p>

                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse ($recoveryCodes as $recoveryCode)
                        <code class="rounded-lg bg-gray-100 px-3 py-2 text-center text-sm dark:bg-white/5">{{ $recoveryCode }}</code>
                    @empty
                        <p class="text-sm text-gray-500">No recovery codes remain. Generate a new set.</p>
                    @endforelse
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <x-filament::button color="gray" wire:click="regenerateRecoveryCodes" wire:confirm="Replace all existing recovery codes?">
                        Regenerate recovery codes
                    </x-filament::button>
                </div>
            </div>

            <form wire:submit="disable" class="mt-8 max-w-md space-y-3 border-t border-gray-200 pt-6 dark:border-white/10">
                <h3 class="text-sm font-semibold">Disable two-factor authentication</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Confirm your password to turn off two-factor authentication.</p>

                <input
                    type="password"
                    wire:model="password"
                    autocomplete="current-password"
                    placeholder="Current password"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-950"
                >

                @error('password')
                    <p class="text-sm text-danger-600">{{ $message }}</p>
                @enderror

                <x-filament::button color="danger" type="submit">
                    Disable
                </x-filament::button>
            </form>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-lg font-semibold">Set up two-factor authentication</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Scan the QR code with an authenticator app, then enter the 6-digit code to confirm.
                </p>

                @if ($qrCodeDataUri)
                    <div class="mt-6 flex justify-center rounded-xl bg-white p-4">
                        <img src="{{ $qrCodeDataUri }}" alt="Two-factor authentication QR code" width="220" height="220">
                    </div>
                @endif

                @if ($secret)
                    <p class="mt-4 text-xs text-gray-500">Manual setup key</p>
                    <code class="mt-1 block break-all rounded-lg bg-gray-100 px-3 py-2 text-center text-sm tracking-widest dark:bg-white/5">
                        {{ $secret }}
                    </code>
                @endif
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-lg font-semibold">Confirm your authenticator</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Enter the code currently shown in your authenticator app.
                </p>

                <form wire:submit="confirm" class="mt-6 space-y-3">
                    <input
                        type="text"
                        wire:model="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        placeholder="000000"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-center text-2xl tracking-[0.4em] dark:border-white/10 dark:bg-gray-950"
                    >

                    @error('code')
                        <p class="text-sm text-danger-600">{{ $message }}</p>
                    @enderror

                    <x-filament::button type="submit">
                        Enable two-factor authentication
                    </x-filament::button>
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
