<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Form;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Allocation;
use App\Models\Server;
use App\Models\Egg;
use App\Models\Node;
use App\Models\User;
use App\Services\ServerApiService;
use App\Services\TranslatorAPIService;
use App\Filament\Resources\ServerResource;
use App\Components\NumberConverter;
use Spatie\DiscordAlerts\DiscordAlert;
use CodeWithDennis\SimpleAlert\Components\Forms\SimpleAlert;
use Exception;
use TypeError;

class CreateServer extends CreateRecord
{
    protected static string $resource = ServerResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('server.create')) {
            abort(403);
        }
        parent::mount();
    }

    public function getTitle(): string
    {
        return 'サーバー作成';
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
                SimpleAlert::make('SettingResourceLimit')
                    ->title('必要な設定が行われていません！')
                    ->description('管理者にお問い合わせください。')
                    ->danger()
                    ->columnSpanFull()
                    ->visible(auth()->user()->resource_limits === null),

                Wizard::make([
                    Step::make('basic-settings')
                        ->label('サーバー基本設定')
                        ->schema([
                            TextInput::make('name')
                                ->label('サーバー名')
                                ->required()
                                ->autocomplete(false)
                                ->suffixAction(
                                    Action::make('random')
                                        ->label('ランダム生成')
                                        ->icon('tabler-arrows-random')
                                        ->action(fn (callable $set) => $set('name', Str::random()))
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
                                ->options(function (callable $get) {
                                    $node = $get('node');
                                    $query = Allocation::where('node_id', $node);
                                    $query->where('assigned', false);
                                    if (!auth()->user()->hasRole('admin')) {
                                        $query->where('public', true);
                                    }
                                    return $query->get()
                                        ->mapWithKeys(fn ($allocation) => [$allocation->id => "{$allocation->alias}:{$allocation->port}"])
                                        ->toArray();
                                })
                                ->required(),
                        ]),

                    Step::make('egg-and-docker-settings')
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
                                    $eggValues = $get('egg_variables') ?? [];
                                    $eggRecord = Egg::where('egg_id', $eggId)->first();
                                    $eggMetas = $eggRecord->egg_variables ?? [];
                                    $fields = [];
                                    $decode = is_array($eggMetas) ? $eggMetas : json_decode($eggMetas, true);
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
                        ])
                        ->columns(1)
                        ->visible(fn ($livewire) => $livewire instanceof CreateRecord),

                    Step::make('resource-settings')
                        ->label('リソースの設定')
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

                    Step::make('other-settings')
                        ->label('その他の設定')
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
                            Hidden::make('user')
                                ->default(auth()->id())
                                ->required(),
                            Hidden::make('slug')
                                ->default(fn (callable $get) => Str::slug($get('external_id') ?? Str::random()))
                                ->required(),
                            Hidden::make('status')
                                ->default('installing')
                                ->required(),
                        ])
                        ->columns(2)
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button type="submit" size="sm">Create</x-filament::button>
                BLADE)))
                ->columnSpanFull()
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        DB::beginTransaction();
        /** @var Server $record */
        $record = parent::handleRecordCreation($data);
        try {
            $res = (new ServerApiService())->CreateServer($record);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        Allocation::query()->where('id', $record->getAttribute('allocation_id'))->update(['assigned' => 1]);
        $record->update([
            'uuid' => $res['server']['attributes']['uuid'],
            'status' => $res['server']['attributes']['status'],
        ]);
        DB::commit();
        activity()
            ->performedOn($record)
            ->causedBy(auth()->user())
            ->withProperties(['level' => 'info'])
            ->log('サーバーを作成しました');
        Notification::make()
            ->title('サーバー作成に成功しました')
            ->success()
            ->send();
        if (config('discord-alerts.webhook_urls.default')) {
            (new DiscordAlert())->to(config('discord-alerts.webhook_urls.default'))
            ->message("", [
                [
                    "title" => "サーバー作成に成功しました",
                    "description" => "{$record->name} を作成しました\nインストール完了までお待ち下さい",
                    "color" => 0x00ff00,
                ]
            ]);
        }
        return $record;
    }

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();
        $data['start_on_completion'] = isset($data['start_on_completion']) ? 1 : 0;
        try {
            $record = $this->handleRecordCreation($data);
        } catch (Exception $e) {
            Log::error($e);
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
            $this->redirect(ServerResource::getUrl('index'));
            return;
        }
        $this->redirect(ServerResource::getUrl('index'));
    }

    public function getFormActions(): array
    {
        return [];
    }
}
