<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use App\Filament\Resources\ServerResource\Pages;
use App\Models\Server;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;
    protected static ?string $navigationIcon = 'tabler-brand-docker';
    protected static ?string $navigationLabel = 'サーバー';
    protected static ?string $navigationGroup = 'サーバー管理';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if (auth()->check() && auth()->user()->hasRole('admin')) {
            return $query;
        }
        return $query->where('user', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServer::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit' => Pages\EditServer::route('/{record:slug}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('server.view');
    }
}
