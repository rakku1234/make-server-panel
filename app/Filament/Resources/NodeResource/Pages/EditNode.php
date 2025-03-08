<?php

namespace App\Filament\Resources\NodeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\NodeResource;

class EditNode extends EditRecord
{
    protected static string $resource = NodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function mount($record): void
    {
        if (!auth()->user()->can('node.edit')) {
            abort(403);
        }
        parent::mount($record);
    }
}
