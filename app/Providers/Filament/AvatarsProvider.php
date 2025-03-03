<?php

namespace App\Providers\Filament;

use Filament\AvatarProviders\Contracts;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
 
class AvatarsProvider implements Contracts\AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        return 'https://www.gravatar.com/avatar/'.hash('sha256', strtolower(trim($record->getAttribute('email') ?? ''))).'?d=wavatar';
    }
}
