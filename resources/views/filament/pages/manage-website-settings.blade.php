<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit">
                Save website settings
            </x-filament::button>

            <x-filament::button tag="a" href="{{ url('/') }}" target="_blank" color="gray">
                Preview website
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
