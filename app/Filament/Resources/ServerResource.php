<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Forms\Form;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Tables\Actions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\ServerResource\Pages;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Server;
use App\Models\Node;
use App\Models\User;
use App\Jobs\DeleteServerJob;
use App\Services\TranslatorAPI;
use App\Filament\Resources\ServerResource\Pages\EditServer;
use App\Func\NumberConverter;
use CodeWithDennis\SimpleAlert\Components\Forms\SimpleAlert;
use JsonException;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                SimpleAlert::make('suspendedAlert')
                    ->title('サーバーは現在禁止されています。')
                    ->description('管理者にお問い合わせください。')
                    ->warning()
                    ->columnSpanFull()
                    ->visible(fn (callable $get) => $get('status') === 'suspended'),
                SimpleAlert::make('SettingResourceLimit')
                    ->title('必要な設定が行われていません！')
                    ->description('管理者にお問い合わせください。')
                    ->danger()
                    ->columnSpanFull()
                    ->visible(fn () => auth()->user()->resource_limits === null),

                Section::make('サーバー基本情報')
                    ->description('サーバーの基本情報を設定します。')
                    ->collapsible()
                    ->schema([
                        TextInput::make('name')
                            ->label('サーバー名')
                            ->required()
                            ->autocomplete(false)
                            ->suffixAction(
                                Action::make('random')
                                    ->label('ランダム生成')
                                    ->icon('heroicon-s-arrow-path')
                                    ->action(fn (callable $set) => $set('name', Str::random(10)))
                                    ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                            ),
                        TextInput::make('external_id')
                            ->label('外部ID')
                            ->hint('何を意味しているかわからない場合は、空のままにしてください'),
                        TextInput::make('description')
                            ->label('説明')
                            ->autocomplete(false)
                            ->columnSpanFull(),
                        Select::make('node')
                            ->label('ノード')
                            ->hint('サーバーが実行されるノードです')
                            ->options(function () {
                                $query = Node::query();
                                if (!auth()->user()->hasRole('admin')) {
                                    $query->where('public', 1);
                                }
                                return $query->pluck('name', 'node_id');
                            })
                            ->required()
                            ->reactive(),
                        Select::make('allocation_id')
                            ->label('割り当て')
                            ->hint('サーバーのIPアドレスとポートです')
                            ->reactive()
                            ->options(function (callable $get, $livewire) {
                                $node = $get('node');
                                if (!$node) {
                                    return [];
                                }
                                $query = Allocation::query();
                                $query->where('node_id', $node);
                                if ($livewire instanceof EditServer) {
                                    $allocationId = $get('allocation_id');
                                    $query->where(function ($query) use ($allocationId) {
                                        $query->where('assigned', false)
                                            ->where('id', $allocationId);
                                        if (!auth()->user()->hasRole('admin')) {
                                            $query->where('public', true);
                                        }
                                    });
                                } else {
                                    $query->where('assigned', false);
                                    if (!auth()->user()->hasRole('admin')) {
                                        $query->where('public', true);
                                    }
                                }
                                return $query->get()
                                    ->mapWithKeys(function ($allocation) {
                                        return [
                                            $allocation->id => "{$allocation->alias}:{$allocation->port}",
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) {
                                $allocation = Allocation::find($value);
                                return $allocation ? "{$allocation->alias}:{$allocation->port}" : $value;
                            })
                            ->required(),
                    ]),

                Section::make('Egg & Docker 設定')
                    ->description('サーバーのEggとDocker Imageを設定します。')
                    ->schema([
                        Select::make('egg')
                            ->label('Egg')
                            ->hint('サーバーのテンプレートです')
                            ->options(function () {
                                return (auth()->user()->hasRole('admin')
                                    ? Egg::all()
                                    : Egg::where('public', true)->get())
                                    ->pluck('name', 'egg_id');
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $query = Egg::query();
                                $query->where('egg_id', $state);
                                if (!auth()->user()->hasRole('admin')) {
                                    $query->where('public', true);
                                }
                                $egg = $query->first();
                                if ($egg) {
                                    $dockerImages = is_array($egg->docker_images) ? $egg->docker_images : [];
                                    $images = array_values($dockerImages);
                                    $set('docker_image', count($images) > 0 ? $images[0] : null);
                                    $variables = json_decode($egg->egg_variables, true);
                                    $values = [];
                                    $metadata = [];
                                    foreach ($variables as $variable) {
                                        if (isset($variable['env_variable'])) {
                                            $envVar = $variable['env_variable'];
                                            $values[$envVar] = $variable['default_value'];
                                            $metadata[$envVar] = [
                                                'description' => $variable['description'],
                                                'user_editable' => $variable['user_editable'],
                                                'user_viewable' => $variable['user_viewable'],
                                                'rules' => $variable['rules'],
                                            ];
                                        }
                                    }
                                    $set('egg_variables', $values);
                                    $set('egg_variables_meta', $metadata);
                                } else {
                                    $set('egg_variables', []);
                                    $set('egg_variables_meta', []);
                                    $set('docker_image', null);
                                }
                            })
                            ->required(),
                        Select::make('docker_image')
                            ->label('Docker Image')
                            ->hint('Docker Image')
                            ->visible(fn (callable $get) => !empty($get('egg')))
                            ->options(function (callable $get) {
                                $eggId = $get('egg');
                                $egg = Egg::find($eggId);
                                $dockerImages = $egg->docker_images;
                                if (is_array($dockerImages)) {
                                    return array_combine($dockerImages, $dockerImages);
                                }
                                return [];
                            })
                            ->required(),
                        Placeholder::make('')
                            ->content('Eggの環境変数を設定してください。')
                            ->columnSpanFull()
                            ->visible(fn (callable $get) => !empty($get('egg_variables'))),
                        Group::make()
                            ->schema(function (callable $get, $livewire) {
                                $eggId = $get('egg') ?? ($livewire instanceof EditServer ? data_get($livewire->record, 'egg') : null);
                                $eggValues = $livewire instanceof EditServer
                                    ? data_get($livewire->record, 'egg_variables', [])
                                    : ($get('egg_variables') ?? []);
                                $eggRecord = Egg::where('egg_id', $eggId)->first();
                                $eggMetas = $eggRecord ? $eggRecord->egg_variables : [];
                                $fields = [];
                                $count = 0;
                                foreach ($eggValues as $key => $value) {
                                    if (isset($eggMetas[$key])) {
                                        $meta = $eggMetas[$key];
                                    } else {
                                        $decode = is_array($eggMetas) ? $eggMetas : json_decode($eggMetas, true);
                                        $meta = $decode[$count];
                                    }
                                    $input = TextInput::make("egg_variables.{$key}")
                                        ->label($key)
                                        ->hint(new TranslatorAPI()->translate($meta['description'], 'en', request()->getPreferredLanguage()))
                                        ->default($value)
                                        ->reactive();
                                    if (isset($meta['user_viewable']) && !$meta['user_viewable']) {
                                        $input->hidden();
                                    }
                                    if (isset($meta['user_editable']) && !$meta['user_editable']) {
                                        $input->disabled();
                                    }
                                    if (isset($meta['rules'])) {
                                        $input->rules($meta['rules']);
                                    }
                                    $fields[] = $input;
                                    $count++;
                                }
                                return $fields;
                            })
                            ->visible(fn (callable $get) => !empty($get('egg'))),
                    ])
                    ->columns(1)
                    ->visible(fn ($livewire) => $livewire instanceof CreateRecord),

                Section::make('リソース設定')
                    ->description('サーバーのリソースを設定します。')
                    ->collapsible()
                    ->schema([
                        TextInput::make('limits.cpu')
                            ->label('CPU')
                            ->hint('コア単位')
                            ->reactive()
                            ->minValue(1)
                            ->maxValue(function (callable $get) {
                                $user = User::where('panel_user_id', auth()->user()->panel_user_id)->first();
                                $maxCpu = collect($user->resource_limits)->firstWhere('node_key', $get('node'))['max_cpu'];
                                if ($maxCpu === -1) {
                                    return null;
                                }
                                $servers = Server::where('node', $get('node'))->get();
                                $totalCpu = 0;
                                foreach ($servers as $server) {
                                    $limits = $server->limits;
                                    $cpu = $limits['cpu'];
                                    $totalCpu += $cpu;
                                }
                                $totalCpu = NumberConverter::convertCpuCore($totalCpu);
                                $maxCpu = NumberConverter::convertCpuCore($maxCpu);
                                return max($maxCpu - $totalCpu, 0);
                            })
                            ->suffix('コア')
                            ->default(0)
                            ->numeric()
                            ->dehydrateStateUsing(fn($state) => NumberConverter::convertCpuCore((float)$state, false))
                            ->formatStateUsing(fn($state) => NumberConverter::convertCpuCore($state))
                            ->required(),
                        TextInput::make('limits.memory')
                            ->label('メモリ')
                            ->hint('MB単位 (1GB = 1000MB)')
                            ->reactive()
                            ->minValue(1)
                            ->maxValue(function (callable $get) {
                                $user = User::where('panel_user_id', auth()->user()->panel_user_id)->first();
                                $maxMemory = collect($user->resource_limits)->firstWhere('node_key', $get('node'))['max_memory'] ?? null;
                                if ($maxMemory === -1) {
                                    return null;
                                }
                                $servers = Server::where('node', $get('node'))->get();
                                $totalMemory = 0;
                                foreach ($servers as $server) {
                                    $limits = $server->limits;
                                    $memory = $limits['memory'];
                                    $totalMemory += $memory;
                                }
                                $maxMemory = NumberConverter::convert($maxMemory, 'MiB', 'MB');
                                $totalMemory = NumberConverter::convert($totalMemory, 'MiB', 'MB');
                                return max($maxMemory - $totalMemory, 0);
                            })
                            ->suffix('MB')
                            ->default(0)
                            ->numeric()
                            ->dehydrateStateUsing(fn($state) => NumberConverter::convert((float)$state, 'MB', 'MiB'))
                            ->formatStateUsing(fn($state) => NumberConverter::convert($state, 'MiB', 'MB'))
                            ->required(),
                        TextInput::make('limits.swap')
                            ->label('スワップ')
                            ->suffix('MB')
                            ->hint('MB単位 (1GB = 1000MB)')
                            ->default(-1)
                            ->numeric()
                            ->readOnly()
                            ->required(),
                        TextInput::make('limits.disk')
                            ->label('ディスク')
                            ->hint('MB単位 (1GB = 1000MB)')
                            ->suffix('MB')
                            ->reactive()
                            ->minValue(1)
                            ->maxValue(function (callable $get) {
                                $user = User::where('panel_user_id', auth()->user()->panel_user_id)->first();
                                $maxDisk = collect($user->resource_limits)->firstWhere('node_key', $get('node'))['max_disk'] ?? null;
                                $servers = Server::where('node', $get('node'))->get();
                                $totalDisk = 0;
                                foreach ($servers as $server) {
                                    $limits = $server->limits;
                                    $disk = $limits['disk'];
                                    $totalDisk += $disk;
                                }
                                $maxDisk = NumberConverter::convert($maxDisk, 'MiB', 'MB');
                                $totalDisk = NumberConverter::convert($totalDisk, 'MiB', 'MB');
                                if ((int)$maxDisk === -1) {
                                    return null;
                                }
                                if ($totalDisk > $maxDisk) {
                                    return 0;
                                }
                                return max($maxDisk - $totalDisk, 0);
                            })
                            ->default(0)
                            ->numeric()
                            ->dehydrateStateUsing(fn($state) => NumberConverter::convert((float)$state, 'MB', 'MiB', false, 0))
                            ->formatStateUsing(fn($state) => NumberConverter::convert($state, 'MiB', 'MB', false, 0))
                            ->required(),
                        TagsInput::make('limits.threads')
                            ->label('CPUピニング')
                            ->hint('コアを選択してください (わからない場合は空のままにしてください)')
                            ->placeholder('コアを指定')
                            ->disabled(),
                        TextInput::make('limits.io')
                            ->label('Block I/O')
                            ->hint('Docker Block I/O (わからない場合は変えないでください)')
                            ->minValue(10)
                            ->maxValue(1000)
                            ->default(500)
                            ->numeric()
                            ->required(),
                        ToggleButtons::make('limits.oom_killer')
                            ->label('OOM Killer')
                            ->options([
                                'true' => '有効',
                                'false' => '無効',
                            ])
                            ->default('true')
                            ->disabled()
                            ->inline(),
                    ])
                    ->columns(2),

                Section::make('その他設定')
                    ->collapsible()
                    ->schema([
                        ToggleButtons::make('start_on_completion')
                            ->label('自動起動')
                            ->hint('インストール完了後にサーバーを自動で起動します')
                            ->options([
                                'true' => '有効',
                                'false' => '無効',
                            ])
                            ->default('true')
                            ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                            ->required()
                            ->inline(),
                        TextInput::make('feature_limits.databases')
                            ->label('データベース')
                            ->hint('データベースの数')
                            ->default(0)
                            ->readOnly()
                            ->required(),
                        TextInput::make('feature_limits.allocations')
                            ->label('追加割り当て')
                            ->hint('追加割り当ての数')
                            ->default(0)
                            ->readOnly()
                            ->required(),
                        TextInput::make('feature_limits.backups')
                            ->label('バックアップ')
                            ->hint('バックアップの数')
                            ->default(3)
                            ->readOnly()
                            ->required(),
                    ])
                    ->columns(2),
                Hidden::make('user')
                    ->default(fn () => auth()->id())
                    ->required(),
                Hidden::make('slug')
                    ->default(fn (callable $get) => Str::slug($get('external_id') ?? Str::random()))
                    ->required(),
                Hidden::make('status')
                    ->default('installing')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->poll('180s')
            ->deferLoading()
            ->striped()
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
                    ->formatStateUsing(fn ($state, $record) => $state ?: $record->name),
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
                TextColumn::make('egg')
                    ->label('Egg名')
                    ->formatStateUsing(fn ($state, $record) => Egg::where('egg_id', $record->egg)->first()->name ?? "Egg情報がありません (Egg: {$record->egg})"),
                TextColumn::make('node')
                    ->label('ノード')
                    ->formatStateUsing(fn ($state, $record) => Node::where('node_id', $record->node)->first()->name ?? "ノード情報がありません (ノード: {$record->node})"),
            ])
            ->filters([
                SelectFilter::make('node')
                    ->label('ノード')
                    ->options(Node::pluck('name', 'node_id'))
                    ->default(Node::where('public', true)->first()?->node_id),
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
                Actions\EditAction::make()
                    ->label('編集')
                    ->visible(fn () => auth()->user()->hasPermissionTo('servers.edit')),
                Actions\DeleteAction::make()
                    ->label('削除')
                    ->visible(fn () => auth()->user()->hasPermissionTo('servers.delete'))
                    ->action(function ($record) {
                        DeleteServerJob::dispatch($record->uuid);
                        activity()
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'level' => 'info',
                            ])
                            ->log('サーバーを削除します');
                    }),
                /*Actions\ViewAction::make()
                    ->label('コンソール')
                    ->url(fn () => url(config('panel.api_url').'/server/')),*/
            ]);
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
        return auth()->check() && auth()->user()->hasPermissionTo('servers.view');
    }
}
