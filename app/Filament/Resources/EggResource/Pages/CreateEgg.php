<?php

namespace App\Filament\Resources\EggResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\EggResource;

class CreateEgg extends CreateRecord
{
    protected static string $resource = EggResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('egg.create')) {
            abort(403);
        }
        parent::mount();
    }
}
