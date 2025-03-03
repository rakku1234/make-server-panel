<?php

namespace App\Filament\Resources\NodeResource\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\NodeResource;

class ListNode extends ListRecords
{
    protected static string $resource = NodeResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('nodes.view')) {
            abort(403);
        }
        parent::mount();
    }
}
