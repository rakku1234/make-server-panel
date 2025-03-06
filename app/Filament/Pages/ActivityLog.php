<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use App\Providers\Filament\AvatarsProvider;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.activity-log';

    protected static ?string $navigationGroup = 'ユーザー管理';

    protected static ?string $navigationLabel = 'アクティビティログ';

    protected static ?int $navigationSort = 2;

    public function table(Table $table): Table
    {
        $query = Activity::query();
        if (!auth()->user()->hasRole('admin')) {
            $query = $query->where('causer_id', auth()->id());
        }
        return $table
            ->query($query)
            ->columns([
                ImageColumn::make('causer.avatar')
                    ->label('アバター')
                    ->circular()
                    ->state(fn ($record) => (new AvatarsProvider())->get($record->causer))
                    ->width(40),
                TextColumn::make('causer.name')
                    ->label('ユーザー'),
                BadgeColumn::make('properties.level')
                    ->label('レベル')
                    ->colors([
                        'success' => fn ($state) => $state === 'info',
                        'warning' => fn ($state) => $state === 'warning',
                        'danger' => fn ($state) => $state === 'error',
                    ]),
                TextColumn::make('description')
                    ->label('アクション'),
                TextColumn::make('created_at')
                    ->label('日時')
                    ->dateTime('Y年m月d日 H時i分')
                    ->timezone(auth()->user()->timezone)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }
}
