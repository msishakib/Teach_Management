<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        @if (! $this->readOnly && auth()->user()?->student)
            <x-filament::button type="submit">Save profile</x-filament::button>
        @endif
    </form>
</x-filament-panels::page>
