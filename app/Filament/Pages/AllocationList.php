<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use App\Models\Allocation;
use App\Models\Node;

class AllocationList extends Page implements HasTable
{
    use InteractsWithTable;
    protected static string $view = 'filament.pages.allocation-list';
    protected static ?string $navigationIcon = 'tabler-server-cog';
    protected static ?string $navigationLabel = 'アロケーション';
    protected static ?string $navigationGroup = 'サーバー管理';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = "allocations";
    protected static ?string $title = "アロケーションリスト";

    public function mount(): void
    {
        if (!auth()->user()->can('allocation.view')) {
            abort(403);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Allocation::query())
            ->groups([
                Group::make('node_id')
                    ->label('ノード')
                    ->getTitleFromRecordUsing(fn ($record) => Node::where('node_id', $record->node_id)->first()->name)
            ])
            ->defaultGroup('node_id')
            ->columns([
                TextColumn::make('alias')
                    ->label('エイリアス'),
                TextColumn::make('port')
                    ->label('ポート'),
                ToggleColumn::make('assigned')
                    ->label('割り当て'),
                ToggleColumn::make('public')
                    ->label('公開'),
            ])
            ->filters([
                SelectFilter::make('node_id')
                    ->label('ノード')
                    ->options(Node::pluck('name', 'node_id')),
                SelectFilter::make('public')
                    ->label('公開')
                    ->options([
                        true => '公開',
                        false => '非公開',
                    ]),
                ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('allocation.view');
    }
}
