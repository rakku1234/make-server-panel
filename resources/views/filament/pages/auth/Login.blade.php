<x-filament-panels::page.simple>
    <x-filament-panels::form wire:submit="authenticate">
        @php /** @var App\Filament\Pages\Auth\Login $this */ @endphp
        {{ $this->form }}
        <button type="submit" style="width: 100%; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; color: white; background-color: #3b82f6; border-radius: 0.5rem; border: none; transition: background-color 0.2s ease-in-out;" onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'" wire:loading.attr="disabled">
            <span wire:loading.remove>ログイン</span>
            <span wire:loading>ログイン中...</span>
        </button>
    </x-filament-panels::form>
</x-filament-panels::page.simple>
