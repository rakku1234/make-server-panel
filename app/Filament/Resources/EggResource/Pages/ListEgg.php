<?php

namespace App\Filament\Resources\EggResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\EggResource;

class ListEgg extends ListRecords
{
    protected static string $resource = EggResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('egg.view')) {
            abort(403);
        }
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Egg一覧';
    }
}
