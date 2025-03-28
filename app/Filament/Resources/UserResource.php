<?php

namespace App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Pages\CreateRecord;
use Filament\Tables\Columns\ImageColumn;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Node;
use App\Providers\Filament\AvatarsProvider;
use App\Components\NumberConverter;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationLabel = 'ユーザー';
    protected static ?string $navigationGroup = 'ユーザー管理';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('基本情報')
                    ->schema([
                        TextInput::make('name')
                            ->label('ユーザー名')
                            ->required(),
                        TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->required(),
                        TextInput::make('password')
                            ->label('パスワード')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                            ->dehydrated(fn ($state) => filled($state)),
                    ])
                    ->columns(3),
                Section::make('権限')
                    ->schema([
                        Select::make('roles')
                            ->label('ロール')
                            ->preload()
                            ->relationship('roles', 'name'),
                        Select::make('permissions')
                            ->label('パーミッション')
                            ->preload()
                            ->reactive()
                            ->multiple()
                            ->relationship('permissions', 'name', function ($query, callable $get) {
                                $query->whereIn('id', function ($subquery) {
                                    $subquery->select('permission_id')
                                        ->from('role_has_permissions');
                                });
                                $selectedRoles = $get('roles');
                                if (is_array($selectedRoles)) {
                                    $excludedPermissionIds = Role::whereIn('id', $selectedRoles)->with('permissions')->get()->pluck('permissions')->flatten()->pluck('id')->toArray();
                                    if (!empty($excludedPermissionIds)) {
                                        $query->whereNotIn('id', $excludedPermissionIds);
                                    }
                                }
                                return $query;
                            }),
                    ])
                    ->columns(),

                Repeater::make('resource_limits')
                    ->label('ノードごとのリソース制限')
                    ->schema([
                        Hidden::make('node_key'),
                        TextInput::make('node_name')
                            ->label('ノード')
                            ->disabled(),
                        TextInput::make('max_cpu')
                            ->label('最大CPU容量')
                            ->numeric()
                            ->suffix('コア')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state === null ? null : ($state === -1 ? -1 : NumberConverter::convertCpuCore($state)))
                            ->dehydrateStateUsing(fn ($state) => (int)$state === -1 ? -1 : NumberConverter::convertCpuCore((float)$state, false)),
                        TextInput::make('max_memory')
                            ->label('最大メモリ容量')
                            ->numeric()
                            ->suffix('MB')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state === null ? null : ($state === -1 ? -1 : NumberConverter::convert($state, 'MiB', 'MB')))
                            ->dehydrateStateUsing(fn ($state) => $state === -1 ? -1 : NumberConverter::convert((float)$state, 'MB', 'MiB')),
                        TextInput::make('max_disk')
                            ->label('最大ディスク容量')
                            ->numeric()
                            ->suffix('MB')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state === null ? null : ($state === -1 ? -1 : NumberConverter::convert($state, 'MiB', 'MB')))
                            ->dehydrateStateUsing(fn ($state) => $state === -1 ? -1 : NumberConverter::convert((float)$state, 'MB', 'MiB')),
                    ])
                    ->columns(3)
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $current = $get('resource_limits');
                        $nodes = Node::all();
                        if (!empty($current)) {
                            foreach ($current as $index => $row) {
                                if ($row['node_key'] !== null) {
                                    $node = $nodes->firstWhere('node_id', $row['node_key']);
                                    if ($node) {
                                        $current[$index]['node_name'] = $node->name;
                                    }
                                } else {
                                    $values = [];
                                    foreach ($nodes as $node) {
                                        $values[] = [
                                            'node_key' => $node->node_id,
                                            'node_name' => $node->name,
                                        ];
                                    }
                                    $set('resource_limits', $values);
                                    return;
                                }
                            }
                            $set('resource_limits', $current);
                        } else {
                            $record = $get('record') ?? null;
                            $limits = [];
                            if ($record) {
                                $limits = $record->resource_limits;
                            }
                            $values = [];
                            foreach ($nodes as $node) {
                                $nodeId = $node->node_id;
                                $nodeLimit = $limits[$nodeId] ?? [];
                                $values[] = [
                                    'node_key'    => $nodeId,
                                    'node_name'   => $node->name,
                                    'max_cpu'     => $nodeLimit['max_cpu'] ?? 0,
                                    'max_memory'  => $nodeLimit['max_memory'] ?? 0,
                                    'max_disk'    => $nodeLimit['max_disk'] ?? 0,
                                ];
                            }
                            $set('resource_limits', $values);
                        }
                    })
                    ->columns()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->state(fn ($record) => (new AvatarsProvider())->get($record))
                    ->width(40),
                TextColumn::make('name')
                    ->label('ユーザー名'),
                TextColumn::make('roles')
                    ->label('ロール')
                    ->formatStateUsing(fn ($state, $record) => $record->getRoleNames()->join(', ')),
                TextColumn::make('timezone')
                    ->label('タイムゾーン')
                    ->formatStateUsing(fn ($state, $record) => $record->timezone),
                TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime('Y年m月d日 H時i分')
                    ->timezone(auth()->user()->timezone),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('user.view');
    }
}
