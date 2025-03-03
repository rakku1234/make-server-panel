<?php

namespace App\Filament\Resources\NodeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\NodeResource;

class CreateNode extends CreateRecord
{
    protected static string $resource = NodeResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('nodes.create')) {
            abort(403);
        }
    }
}
