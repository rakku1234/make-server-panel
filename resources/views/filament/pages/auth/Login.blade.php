<x-filament-panels::page.simple>
    <x-filament-panels::form wire:submit="authenticate">
        @php /** @var App\Filament\Pages\Auth\Login $this */ @endphp
        {{ $this->form }}
        <x-filament::button type="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>ログイン</span>
            <span wire:loading>ログイン中...</span>
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page.simple>
