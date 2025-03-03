<x-filament-panels::page>
    <?php /** @var App\Filament\Pages\Auth\Profile $this */ ?>
    {{ $this->form }}
    <div class="mt-4">
        <x-filament::button wire:click="submit">
            保存
        </x-filament::button>
    </div>
</x-filament-panels::page>
