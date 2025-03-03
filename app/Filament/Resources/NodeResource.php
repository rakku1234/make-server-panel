<?php

namespace App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Resources\NodeResource\Pages;
use App\Models\Node;
use CodeWithDennis\SimpleAlert\Components\Forms\SimpleAlert;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationLabel = 'ノード';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'サーバー管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                SimpleAlert::make('public_msg')
                    ->title('ノードの編集')
                    ->description('ノードを編集してもpelicanには反映されません。')
                    ->warning()
                    ->columnSpanFull(),
                TextInput::make('node_id')
                    ->label('ノードID')
                    ->required(),
                TextInput::make('name')
                    ->label('ノード名')
                    ->required(),
                TextInput::make('description')
                    ->label('説明'),
                Toggle::make('public')
                    ->label('公開'),
                Toggle::make('maintenance_mode')
                    ->label('メンテナンスモード'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('ノード名'),
                ToggleColumn::make('public')
                    ->label('公開'),
                ToggleColumn::make('maintenance_mode')
                    ->label('メンテナンスモード'),
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
            'index' => Pages\ListNode::route('/'),
            'create' => Pages\CreateNode::route('/create'),
            'edit' => Pages\EditNode::route('/{record:slug}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('nodes.view');
    }
}
