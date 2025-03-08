<?php

namespace App\Filament\Resources\ServerResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\ResourceLimit;
use App\Filament\Resources\ServerResource;

class ListServer extends ListRecords
{
    protected static string $resource = ServerResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('server.view')) {
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
        return 'サーバー一覧';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ResourceLimit::class,
        ];
    }
}
