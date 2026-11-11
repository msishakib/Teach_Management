<x-filament-panels::page>
    <form wire:submit.prevent="saveMarks">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="button" color="gray" wire:click="loadRoster">
                Load students
            </x-filament::button>

            @if ($this->rosterLoaded)
                <x-filament::button type="button" wire:click="saveMarks" :disabled="$this->isReadonlyGrid()">
                    Save marks
                </x-filament::button>

                <x-filament::button type="button" color="gray" wire:click="downloadTemplate" tag="button">
                    Download CSV template
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
