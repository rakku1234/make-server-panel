<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Actions as TableActions;
use App\Components\NumberConverter;
use App\Filament\Widgets\ResourceLimit;
use App\Filament\Resources\ServerResource;
use App\Jobs\DeleteServerJob;
use App\Models\Node;
use App\Models\Allocation;

class ListServer extends ListRecords
{
    protected static string $resource = ServerResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('server.view')) {
            abort(403);
        }
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'サーバー一覧';
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('180s')
            ->deferLoading()
            ->striped()
            ->groups([
                Group::make('node')
                    ->label('ノード')
                    ->getTitleFromRecordUsing(fn ($record) => Node::where('node_id', $record->node)->first()->name)
            ])
            ->defaultGroup('node')
            ->columns([
                TextColumn::make('status')
                    ->label('ステータス')
                    ->placeholder('不明')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'installing' => 'info',
                        'starting'   => 'warning',
                        'running'    => 'success',
                        'offline'    => 'warning',
                        'suspended'  => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'installing' => 'インストール中',
                        'starting'   => '起動中',
                        'running'    => '実行中',
                        'offline'    => '停止中',
                        'suspended'  => '禁止中',
                        default      => '不明',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'installing' => 'heroicon-s-arrow-path',
                        'starting'   => 'heroicon-s-arrow-path',
                        'running'    => 'heroicon-s-check-circle',
                        'offline'    => 'heroicon-s-x-circle',
                        'suspended'  => 'heroicon-s-x-circle',
                        default      => 'heroicon-s-question-mark-circle',
                    }),
                TextColumn::make('name')
                    ->label('サーバー名')
                    ->formatStateUsing(fn ($state) => $state),
                TextColumn::make('limits.cpu')
                    ->label('CPU')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        $state === 0 => '無制限',
                        default => NumberConverter::convertCpuCore($state).' コア',
                    }),
                TextColumn::make('limits.memory')
                    ->label('メモリ量')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        empty($state) => '無制限',
                        default => NumberConverter::convert($state, 'MiB', auth()->user()->unit, true),
                    }),
                TextColumn::make('limits.disk')
                    ->label('ディスク')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        empty($state) => '無制限',
                        default => NumberConverter::convert($state, 'MiB', auth()->user()->unit, true),
                    }),
                TextColumn::make('allocation_id')
                    ->label('アドレス')
                    ->formatStateUsing(function ($record) {
                        $allocation = Allocation::where('id', $record->allocation_id)->first();
                        return "$allocation->alias:$allocation->port";
                    }),
            ])
            ->filters([
                SelectFilter::make('node')
                    ->label('ノード')
                    ->options(Node::pluck('name', 'node_id')),
                SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'installing' => 'インストール中',
                        'starting'   => '起動中',
                        'running'    => '実行中',
                        'offline'    => '停止中',
                        'suspended'  => '禁止中',
                    ]),
            ])
            ->actions([
                TableActions\EditAction::make()
                    ->label('編集')
                    ->visible(auth()->user()->hasPermissionTo('server.edit')),
                TableActions\DeleteAction::make()
                    ->label('削除')
                    ->visible(auth()->user()->hasPermissionTo('server.delete'))
                    ->action(function ($record) {
                        DeleteServerJob::dispatch($record->uuid);
                        activity()
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'level' => 'info',
                            ])
                            ->log('サーバーを削除します');
                    }),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ResourceLimit::class,
        ];
    }
}
