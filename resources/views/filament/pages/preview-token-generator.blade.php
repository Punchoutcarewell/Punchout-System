<x-filament-panels::page>
    <form wire:submit="generate">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Generate link
            </x-filament::button>
        </div>
    </form>

    @if ($generatedUrl)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Preview link</x-slot>

            <div class="flex items-center gap-3">
                <x-filament::input.wrapper class="flex-1">
                    <x-filament::input type="text" readonly value="{{ $generatedUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>

                <x-filament::button tag="a" href="{{ $generatedUrl }}" target="_blank" icon="heroicon-o-arrow-top-right-on-square" color="gray">
                    Open
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
