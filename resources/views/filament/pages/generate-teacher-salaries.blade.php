<x-filament-panels::page>
    <form wire:submit.prevent="generate" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-queue-list">
            Queue salary generation
        </x-filament::button>
    </form>

    <div class="mt-6 text-sm text-gray-600 dark:text-gray-300">
        <p>This dispatches a queued, chunked job. Teachers without a salary amount are skipped. Re-running the same month is safe — existing rows are not duplicated.</p>
    </div>
</x-filament-panels::page>
