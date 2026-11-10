<x-filament-panels::page>
    <form wire:submit.prevent="saveAttendance">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="button" color="gray" wire:click="loadRoster">
                Load students
            </x-filament::button>

            @if ($this->rosterLoaded)
                <x-filament::button type="button" wire:click="saveAttendance" :disabled="$this->isReadonlySession()">
                    Save attendance
                </x-filament::button>

                <x-filament::button type="button" color="success" wire:click="finalizeAttendance" :disabled="$this->isReadonlySession()">
                    Save & finalize
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
