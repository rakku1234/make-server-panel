<?php

namespace App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use App\Filament\Resources\AllocationResource\Pages;
use App\Models\Allocation;
use App\Models\Node;

class AllocationResource extends Resource
{
    protected static ?string $model = Allocation::class;
    protected static ?string $navigationIcon = 'tabler-server-cog';
    protected static ?string $navigationLabel = 'アロケーション';
    protected static ?string $navigationGroup = 'サーバー管理';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('alias')
                    ->label('エイリアス')
                    ->disabled(),
                TextInput::make('port')
                    ->label('ポート')
                    ->disabled(),
                Toggle::make('assigned')
                    ->label('割り当て')
                    ->disabled(),
                Toggle::make('public')
                    ->label('公開'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                TextColumn::make('assigned')
                    ->label('割り当て')
                    ->formatStateUsing(fn ($state) => $state ? '割り当て済み' : '未割り当て'),
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAllocation::route('/'),
            'edit' => Pages\EditAllocation::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('allocation.view');
    }
}
