<?php

namespace App\Filament\Resources\AllocationResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\AllocationResource;

class ListAllocation extends ListRecords
{
    protected static string $resource = AllocationResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('allocation.view')) {
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
}
