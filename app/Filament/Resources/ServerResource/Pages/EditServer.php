<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerResource\Pages;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Form;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use App\Services\ServerApiService;
use App\Models\Server;
use App\Models\Egg;
use App\Models\User;
use App\Models\Node;
use App\Models\Allocation;
use App\Services\TranslatorAPIService;
use App\Components\NumberConverter;
use App\Filament\Resources\ServerResource;
use CodeWithDennis\SimpleAlert\Components\Forms\SimpleAlert;
use TypeError;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    public function mount($record): void
    {
        if (!auth()->user()->can('server.edit')) {
            abort(403);
        }
        parent::mount($record);
    }

    public function getTitle(): string
    {
        return 'サーバー編集';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                SimpleAlert::make('suspendedAlert')
                    ->title('サーバーは現在禁止されています。')
                    ->description('管理者にお問い合わせください。')
                    ->warning()
                    ->columnSpanFull()
                    ->visible(fn (callable $get) => $get('status') === 'suspended'),
                Tabs::make('server-tab')
                    ->tabs([
                        Tab::make('basic-server')
                            ->label('サーバー基本設定')
                            ->schema([
                                TextInput::make('name')
                                    ->label('サーバー名'),
                                TextInput::make('description')
                                    ->label('説明'),
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
                                    ->disabled()
                                    ->reactive(),
                                Select::make('allocation_id')
                                    ->label('割り当て')
                                    ->hint('サーバーのIPアドレスとポートです')
                                    ->reactive()
                                    ->options(function (callable $get) {
                                        $node = $get('node');
                                        $query = Allocation::where('node_id', $node);
                                        $allocationId = $get('allocation_id');
                                        $query->where(fn ($query) => $query->where('id', $allocationId)->orWhere('assigned', false));
                                        if (!auth()->user()->hasRole('admin')) {
                                            $query->where('public', true);
                                        }
                                        return $query->get()
                                            ->mapWithKeys(fn ($allocation) => [$allocation->id => "{$allocation->alias}:{$allocation->port}"])
                                            ->toArray();
                                    })
                                    ->required(),
                            ]),
                        Tab::make('egg-and-docker-settings')
                            ->label('Egg & Dockerの設定')
                            ->schema([
                                Select::make('egg')
                                ->label('Egg')
                                ->hint('サーバーのテンプレートです')
                                ->options(function () {
                                    $query = Egg::select(['egg_id', 'name']);
                                    if (!auth()->user()->hasRole('admin')) {
                                        $query->where('public', true);
                                    }
                                    return $query->pluck('name', 'egg_id');
                                })
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $query = Egg::query();
                                    $query->where('egg_id', $state);
                                    if (!auth()->user()->hasRole('admin')) {
                                        $query->where('public', true);
                                    }
                                    $egg = $query->first();
                                    if ($egg) {
                                        $dockerImages = $egg->docker_images ?? [];
                                        $images = array_values($dockerImages);
                                        $set('docker_image', count($images) > 0 ? $images[0] : null);
                                        try {
                                            $variables = json_decode($egg->egg_variables, true);
                                        } catch (TypeError $e) { /** @phpstan-ignore-line */
                                            Log::error($e);
                                            Notification::make()
                                                ->title('エラーが発生しました')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->send();
                                            return redirect()->to('/admin/servers');
                                        }
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
                                ->reactive()
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
                                ->schema(function (callable $get) {
                                    $eggId = $get('egg');
                                    $eggValues = $get('egg_variables');
                                    $eggRecord = Egg::where('egg_id', $eggId)->first();
                                    $eggMetas = $eggRecord->egg_variables;
                                    $fields = [];
                                    try {
                                        $decode = json_decode($eggMetas, true);
                                    } catch (TypeError $e) { /** @phpstan-ignore-line */
                                        Notification::make()
                                            ->title('エラーが発生しました')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                        redirect()->to('/admin/servers');
                                    }
                                    $count = 0;
                                    foreach ($eggValues as $key => $value) {
                                        if (!isset($decode[$count])) {
                                            continue;
                                        }
                                        $meta = $decode[$count];
                                        $input = TextInput::make("egg_variables.{$key}")
                                            ->label($key)
                                            ->hint((new TranslatorAPIService($meta['description'], 'en', request()->getPreferredLanguage()))->translatedText)
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
                            ]),
                        Tab::make('resource-settings')
                            ->label('リソース設定')
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
                            ])
                            ->columns(2)
                    ])
                    ->columnSpanFull()
            ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Server $record */
        (new ServerApiService())->UpdateServer($record);
        return $record;
    }

    public function update(bool $another = false): void
    {
        $data = $this->form->getState();
        $record = $this->handleRecordUpdate($this->record, $data);
        redirect()->to(ServerResource::getUrl('edit', ['record' => $record->getKey()]));
    }
}
