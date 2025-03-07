<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        @php /** @var App\Filament\Pages\Settings $this */ @endphp
        {{ $this->form }}
        <x-filament-panels::form.actions 
            :actions="[
                \Filament\Actions\Action::make('save')
                    ->label('保存')
                    ->submit('save')
            ]"
            alignment="right"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
