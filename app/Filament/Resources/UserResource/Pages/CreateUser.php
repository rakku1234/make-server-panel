<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('users.create')) {
            abort(403);
        }
        parent::mount();
    }
}
