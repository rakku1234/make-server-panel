<?php

namespace App\Filament\Pages;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Enums\Alignment;
use Filament\Notifications\Notification;
use App\Models\Server;
use App\Models\Egg;
use App\Models\Allocation;
use App\Models\Node;
use Exception;

class ServersImportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.servers-import-page';
    protected static ?string $title = 'サーバー情報の取り込み';
    protected static ?string $navigationLabel = 'サーバー情報の取り込み';
    protected static ?string $navigationGroup = 'パネル管理';
    protected static ?int $navigationSort = 2;
    public ?string $importResult = null;

    public function mount(): void
    {
        if (!auth()->user()->hasPermissionTo('server.import')) {
            abort(403);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('サーバー情報の取り込み')
                    ->description('取り込み処理を開始する場合は、以下のボタンをクリックしてください。')
                    ->schema([
                        Placeholder::make('import_result')
                            ->label('取り込み結果')
                            ->content($this->importResult)
                            ->visible($this->importResult !== null)
                            ->extraAttributes(['class' => 'p-4 rounded bg-green-100 dark:bg-green-900 border border-green-300 dark:border-green-600 text-green-800 dark:text-green-100']),
                    ])
                    ->footerActions([
                        Action::make('import')
                            ->label('取り込み開始')
                            ->color('primary')
                            ->action('importServersFromPelican')
                            ->visible($this->importResult === null),
                    ])
                    ->footerActionsAlignment(Alignment::End),
            ]);
    }

    public function importServersFromPelican(): void
    {
        $apiToken = config('panel.api_application_token');

        $nodesApiUrl = config('panel.api_url').'/api/application/nodes';
        $nodesResponse = Http::withToken($apiToken)->get($nodesApiUrl);

        $nodesData = [];

        if ($nodesResponse->successful()) {
            $nodesData = $nodesResponse->json()['data'];

            foreach ($nodesData as $nodeItem) {
                $attributes = $nodeItem['attributes'];
                if (isset($attributes['id']) && Node::where('node_id', $attributes['id'])->exists()) {
                    continue;
                }
                Node::create([
                    'node_id'          => $attributes['id'],
                    'slug'             => $attributes['name'],
                    'uuid'             => $attributes['uuid'],
                    'name'             => $attributes['name'],
                    'description'      => $attributes['description'],
                    'maintenance_mode' => $attributes['maintenance_mode'],
                    'public'           => $attributes['public'],
                    'created_at'       => $attributes['created_at'],
                    'updated_at'       => $attributes['updated_at'],
                ]);
            }
        }

        $allocationsData = [];
        foreach ($nodesData as $nodeItem) {
            $nodeAttributes = $nodeItem['attributes'] ?? [];
            if (!isset($nodeAttributes['id'])) {
                continue;
            }
            $nodeId = $nodeAttributes['id'];
            $allocationsApiUrl = config('panel.api_url').'/api/application/nodes/'.$nodeId.'/allocations';
            $allocationsResponse = Http::withToken($apiToken)->get($allocationsApiUrl);
            if ($allocationsResponse->successful()) {
                $data = $allocationsResponse->json();
                $nodeAllocations = $data['data'];
                $allocationsData = array_merge($allocationsData, $nodeAllocations);

                foreach ($nodeAllocations as $allocationItem) {
                    $attributes = $allocationItem['attributes'];
                    if (isset($attributes['port']) && Allocation::where('port', $attributes['port'])
                        ->where('node_id', $nodeId)
                        ->exists()) {
                        continue;
                    }
                    Allocation::create([
                        'id'       => $attributes['id'],
                        'alias'    => $attributes['alias'],
                        'port'     => $attributes['port'],
                        'assigned' => $attributes['assigned'],
                        'node_id'  => $nodeId,
                    ]);
                }
            }
        }

        $eggsApiUrl = config('panel.api_url').'/api/application/eggs';
        $eggsResponse = Http::withToken($apiToken)->get($eggsApiUrl);

        $eggsData = [];

        if ($eggsResponse->successful()) {
            $data = $eggsResponse->json();
            $eggsData = $data['data'];

            foreach ($eggsData as $eggItem) {
                $attributes = $eggItem['attributes'];
                $dockerImages = $attributes['docker_images'];
                if (!is_array($dockerImages)) {
                    $dockerImages = [$dockerImages];
                }
                if (isset($attributes['uuid']) && (
                    Egg::where('uuid', $attributes['uuid'])->exists() ||
                    Egg::where('egg_id', $attributes['id'])->exists()
                )) {
                    continue;
                }
                Egg::create([
                    'uuid'          => $attributes['uuid'],
                    'egg_id'        => $attributes['id'],
                    'name'          => $attributes['name'],
                    'description'   => $attributes['description'],
                    'docker_images' => $dockerImages,
                    'slug'          => $attributes['name'] ?? Str::random(10),
                    ]
                );
            }
        }

        $serversApiUrl = config('panel.api_url').'/api/application/servers';
        $serversResponse = Http::withToken($apiToken)->get($serversApiUrl);

        $serversData = [];

        if ($serversResponse->successful()) {
            $serversData = $serversResponse->json()['data'];

            foreach ($serversData as $serverItem) {
                $attributes = $serverItem['attributes'];

                if (isset($attributes['uuid']) && Server::where('uuid', $attributes['uuid'])->exists()) {
                    continue;
                }
                try {
                    Server::create([
                    'limits'         => $attributes['limits'],
                    'user'           => $attributes['user'],
                    'egg'            => $attributes['egg'],
                    'feature_limits' => $attributes['feature_limits'],
                    'status'         => $attributes['status'] ?? 'None',
                    'uuid'           => $attributes['uuid'],
                    'name'           => $attributes['name'],
                    'node'           => $attributes['node'],
                    'description'    => $attributes['description'],
                    'allocation_id'  => $attributes['allocation'],
                    'docker_image'   => $attributes['container']['image'],
                    'egg_variables'  => $attributes['container']['environment'],
                    'start_on_completion' => true,
                    'slug'           => $attributes['external_id'] ?? Str::random(10),
                    ]);
                } catch (Exception $e) {
                    Notification::make()
                        ->title('サーバー情報の取り込みに失敗しました')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                    Log::error($e->getMessage());
                }
            }
        }

        $this->importResult = "Eggs (".count($eggsData)."個) とサーバー情報 (".count($serversData)."個) と Allocations (".count($allocationsData)."個) の取り込みが完了しました";
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('server.import');
    }
}
