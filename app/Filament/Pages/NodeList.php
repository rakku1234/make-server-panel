<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use App\Models\Node;

class NodeList extends Page implements HasTable
{
    use InteractsWithTable;
    protected static string $view = 'filament.pages.node-list';
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationLabel = 'ノード';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'サーバー管理';
    protected static ?string $slug = 'nodes';
    protected static ?string $title = 'ノードリスト';

    public function mount(): void
    {
        if (!auth()->user()->can('node.view')) {
            abort(403);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Node::query())
            ->columns([
                TextColumn::make('name')
                    ->label('ノード名'),
                ToggleColumn::make('public')
                    ->label('公開'),
                ToggleColumn::make('maintenance_mode')
                    ->label('メンテナンスモード'),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('node.view');
    }
}
