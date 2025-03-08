<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Filament\Pages\ServersImportPage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Spatie\Permission\Models\Permission;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('imports servers data successfully when authorized', function () {
    config(['panel.api_url' => 'http://panel.example.com']);

    Permission::firstOrCreate(['name' => 'server.import']);
    $user = User::factory()->create();
    $user->givePermissionTo('server.import');
    actingAs($user);

    $nodeId = rand(1, 100);
    $nodeName = 'Node-' . Str::random(8);
    $nodeUuid = Str::uuid()->toString();

    $allocationId = rand(1, 100);
    $allocationPort = rand(1000, 9999);

    $eggId = rand(1, 100);
    $eggUuid = Str::uuid()->toString();
    $eggName = 'Egg-' . Str::random(8);
    $dockerImage = 'nginx:' . rand(1, 10) . '.' . rand(0, 20);

    $serverUuid = Str::uuid()->toString();
    $serverName = 'Server-' . Str::random(8);
    $serverDescription = 'Description-' . Str::random(12);

    Http::fake([
        'http://panel.example.com/api/application/nodes' => Http::response([
            'data' => [
                [
                    'attributes' => [
                        'id' => $nodeId,
                        'name' => $nodeName,
                        'uuid' => $nodeUuid,
                        'description' => 'Description for ' . $nodeName,
                        'maintenance_mode' => (bool)rand(0, 1),
                        'public' => (bool)rand(0, 1),
                        'created_at' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                        'updated_at' => now()->subDays(rand(0, 5))->format('Y-m-d')
                    ]
                ]
            ]
        ], 200),

        "http://panel.example.com/api/application/nodes/{$nodeId}/allocations" => Http::response([
            'data' => [
                [
                    'attributes' => [
                        'id' => $allocationId,
                        'alias' => rand(0, 1) ? 'alias-' . Str::random(5) : '',
                        'port' => $allocationPort,
                        'assigned' => (bool)rand(0, 1),
                        'node' => $nodeId
                    ]
                ]
            ]
        ], 200),

        'http://panel.example.com/api/application/eggs' => Http::response([
            'data' => [
                [
                    'attributes' => [
                        'id' => $eggId,
                        'uuid' => $eggUuid,
                        'name' => $eggName,
                        'description' => 'Description for ' . $eggName,
                        'docker_images' => $dockerImage
                    ]
                ]
            ]
        ], 200),

        'http://panel.example.com/api/application/servers' => Http::response([
            'data' => [
                [
                    'attributes' => [
                        'uuid' => $serverUuid,
                        'limits' => [
                            'memory' => rand(512, 4096),
                            'swap' => rand(0, 1024),
                            'disk' => rand(5000, 20000),
                            'io' => rand(100, 500),
                            'cpu' => rand(50, 300)
                        ],
                        'user' => rand(1, 10),
                        'egg' => $eggId,
                        'feature_limits' => [
                            'databases' => rand(0, 5),
                            'backups' => rand(0, 3)
                        ],
                        'status' => ['running', 'starting', 'stopping', 'offline'][rand(0, 3)],
                        'name' => $serverName,
                        'node' => $nodeId,
                        'description' => $serverDescription,
                        'allocation' => $allocationId,
                        'container' => [
                            'image' => $dockerImage,
                            'environment' => [
                                'STARTUP' => 'echo ' . Str::random(10),
                                'SERVER_JARFILE' => 'server.jar',
                                'MEMORY' => rand(512, 2048) . 'M'
                            ]
                        ]
                    ]
                ]
            ]
        ], 200)
    ]);

    $page = new ServersImportPage();
    $page->mount();
    $page->importServersFromPelican();

    expect($page->importResult)->toMatch('/Eggs \(\d+個\) とサーバー情報 \(\d+個\) と Allocations \(\d+個\) の取り込みが完了しました/');
});

test('rejects unauthorized access', function () {
    Permission::firstOrCreate(['name' => 'server.import']);
    $user = User::factory()->create();
    actingAs($user);
    $this->withoutExceptionHandling();
    expect(fn() => (new ServersImportPage())->mount())->toThrow(HttpException::class);
});
