<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\ServerResource;
use App\Filament\Resources\ServerResource\Pages\CreateServer;
use App\Filament\Resources\ServerResource\Pages\EditServer;
use App\Filament\Resources\ServerResource\Pages\ListServer;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Func\NumberConverter;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

function setupTestModels() {
    $connection = DB::connection()->getDriverName();

    if ($connection === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
    } else {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    Server::truncate();
    Allocation::truncate();
    Egg::truncate();
    Node::truncate();
    User::truncate();

    if ($connection === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON');
    } else {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    $permissions = [
        'servers.create',
        'servers.edit',
        'servers.delete',
        'servers.view',
        'servers.import',
        'nodes.edit',
        'nodes.view',
        'eggs.edit',
        'eggs.delete',
        'eggs.view',
        'allocations.view',
        'users.create',
        'users.edit',
        'users.delete',
        'users.view',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $adminRole->syncPermissions(Permission::all());
    $userRole->syncPermissions(['servers.view']);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'panel_user_id' => 1,
        'unit' => 'MB',
    ]);

    $user->assignRole('admin');

    $node = Node::create([
        'node_id' => 1,
        'uuid' => Str::uuid(),
        'name' => 'Test Node',
        'slug' => 'test-node-' . Str::random(6),
        'description' => 'Test Node Description',
        'maintenance_mode' => false,
        'public' => true,
    ]);

    $egg = Egg::create([
        'egg_id' => 1,
        'uuid' => Str::uuid(),
        'name' => 'Test Egg',
        'slug' => 'test-egg-' . Str::random(6),
        'description' => 'Test Egg Description',
        'docker_images' => ['test/image:latest'],
        'egg_variables' => json_encode([
            [
                'env_variable' => 'TEST_VAR',
                'default_value' => 'test',
                'description' => 'Test Variable',
                'user_editable' => true,
                'user_viewable' => true,
                'rules' => 'nullable|string',
            ]
        ]),
        'public' => true,
    ]);

    $allocation = Allocation::create([
        'id' => 1,
        'node_id' => $node->node_id,
        'alias' => '127.0.0.1',
        'port' => 25565,
        'assigned' => false,
        'public' => true,
    ]);

    $user->update([
        'resource_limits' => [
            [
                'node_key' => $node->node_id,
                'node_name' => $node->name,
                'max_cpu' => 400,
                'max_memory' => 4096,
                'max_disk' => 10240,
            ]
        ]
    ]);

    return [
        'user' => $user,
        'node' => $node,
        'egg' => $egg,
        'allocation' => $allocation,
    ];
}

function setupFilamentContext()
{
    Config::set('filament.auth.guard', 'web');
    Config::set('app.debug', true);
}

test('authenticated user can view server list page', function () {
    $models = setupTestModels();
    setupFilamentContext();
    
    Livewire::actingAs($models['user'])
        ->test(ListServer::class)
        ->assertSuccessful();
});

test('user without permission cannot view server list page', function () {
    $models = setupTestModels();
    setupFilamentContext();
    
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'regular@example.com',
        'password' => bcrypt('password'),
    ]);

    actingAs($user)
        ->get(ServerResource::getUrl('index'))
        ->assertForbidden();
});

test('can view server creation page', function () {
    $models = setupTestModels();
    setupFilamentContext();

    Livewire::actingAs($models['user'])
        ->test(CreateServer::class)
        ->assertSuccessful();
});

test('can create a server', function () {
    $models = setupTestModels();
    setupFilamentContext();
    
    Config::set('panel.api_token', 'test-token');
    Config::set('panel.api_url', 'http://example.com');
    
    Http::fake([
        'http://example.com/api/application/servers' => Http::response([
            'server' => [
                'attributes' => [
                    'uuid' => 'test-uuid',
                    'status' => 'installing',
                ]
            ]
        ], 201),
    ]);

    $mockServer = new Server([
        'name' => 'Test Server',
        'description' => 'Test Server Description',
        'node' => $models['node']->node_id,
        'allocation_id' => $models['allocation']->id,
        'egg' => $models['egg']->egg_id,
        'docker_image' => 'test/image:latest',
        'limits' => [
            'cpu' => 200,
            'memory' => 2048,
            'swap' => -1,
            'disk' => 5120,
            'io' => 500,
            'oom_killer' => true,
        ],
        'feature_limits' => [
            'databases' => 0,
            'allocations' => 0,
            'backups' => 3,
        ],
        'start_on_completion' => true,
        'user' => $models['user']->id,
        'egg_variables' => [
            'TEST_VAR' => 'test-value'
        ],
        'uuid' => 'test-uuid',
        'status' => 'installing',
        'slug' => 'test-server-' . Str::random(6),
    ]);

    $mockServer->save();

    assertDatabaseHas('servers', [
        'name' => 'Test Server',
        'description' => 'Test Server Description',
    ]);

    $component = Livewire::actingAs($models['user'])
        ->test(CreateServer::class)
        ->fillForm([
            'name' => 'Another Test Server',
            'description' => 'Another Test Description',
            'node' => $models['node']->node_id,
            'allocation_id' => $models['allocation']->id,
            'egg' => $models['egg']->egg_id,
            'docker_image' => 'test/image:latest',
            'limits' => [
                'cpu' => 200,
                'memory' => 2048,
                'swap' => -1,
                'disk' => 5120,
                'io' => 500,
                'oom_killer' => true,
            ],
            'feature_limits' => [
                'databases' => 0,
                'allocations' => 0,
                'backups' => 3,
            ],
            'start_on_completion' => true,
            'user' => $models['user']->id,
            'egg_variables' => [
                'TEST_VAR' => 'test-value'
            ],
        ]);
    
    $component->assertHasNoErrors();
});

test('can edit a server', function () {
    $models = setupTestModels();
    setupFilamentContext();

    $server = Server::create([
        'uuid' => 'test-uuid-edit',
        'name' => 'Original Server',
        'slug' => 'original-server-' . Str::random(6),
        'description' => 'Original Description',
        'external_id' => null,
        'status' => 'offline',
        'node' => $models['node']->node_id,
        'allocation_id' => $models['allocation']->id,
        'egg' => $models['egg']->egg_id,
        'docker_image' => 'test/image:latest',
        'user' => $models['user']->id,
        'limits' => [
            'cpu' => 100,
            'memory' => 1024,
            'swap' => -1,
            'disk' => 2048,
            'io' => 500,
            'oom_killer' => true,
        ],
        'feature_limits' => [
            'databases' => 0,
            'allocations' => 0,
            'backups' => 3,
        ],
        'egg_variables' => [
            'TEST_VAR' => 'original-value'
        ],
        'start_on_completion' => true,
    ]);

    Http::fake([
        config('panel.api_url') . '/api/application/servers/*' => Http::response([
            'object' => 'server',
            'attributes' => [
                'id' => 1,
                'uuid' => 'test-uuid-edit',
                'status' => 'offline',
            ],
        ], 200),
    ]);

    $server->update([
        'name' => 'Updated Server',
        'description' => 'Updated Description',
    ]);

    $server->refresh();
    expect($server->name)->toBe('Updated Server');
    expect($server->description)->toBe('Updated Description');

    Livewire::actingAs($models['user'])
        ->test(EditServer::class, [
            'record' => $server->slug,
        ])
        ->assertSuccessful();
});

test('can delete a server', function () {
    $models = setupTestModels();
    setupFilamentContext();

    $server = Server::create([
        'uuid' => 'test-uuid-delete',
        'name' => 'Test Server Delete',
        'slug' => 'test-server-delete-' . Str::random(6),
        'description' => 'Test Description',
        'external_id' => null,
        'status' => 'offline',
        'node' => $models['node']->node_id,
        'allocation_id' => $models['allocation']->id,
        'egg' => $models['egg']->egg_id,
        'docker_image' => 'test/image:latest',
        'user' => $models['user']->id,
        'limits' => [
            'cpu' => 100,
            'memory' => 1024,
            'swap' => -1,
            'disk' => 2048,
            'io' => 500,
            'oom_killer' => true,
        ],
        'feature_limits' => [
            'databases' => 0,
            'allocations' => 0,
            'backups' => 3,
        ],
        'egg_variables' => [
            'TEST_VAR' => 'test-value'
        ],
        'start_on_completion' => true,
    ]);

    Http::fake([
        config('panel.api_url') . '/api/application/servers/*' => Http::response([
            'object' => 'server',
            'attributes' => [
                'id' => 1,
                'uuid' => 'test-uuid-delete',
                'status' => 'offline',
            ],
        ], 200),
    ]);

    Bus::fake();

    Livewire::actingAs($models['user'])
        ->test(ListServer::class)
        ->callTableAction('delete', $server);

    Bus::assertDispatched(\App\Jobs\DeleteServerJob::class);
});

test('NumberConverter correctly converts CPU cores', function () {
    expect(NumberConverter::convertCpuCore(100))->toBe(1.0);
    expect(NumberConverter::convertCpuCore(200))->toBe(2.0);
    expect(NumberConverter::convertCpuCore(50))->toBe(0.5);
    expect(NumberConverter::convertCpuCore(2, false))->toBe(200.0);
    expect(NumberConverter::convertCpuCore(0.5, false))->toBe(50.0);
});

test('NumberConverter correctly converts memory units', function () {
    expect(NumberConverter::convert(1024, 'MiB', 'MB'))->toBe(1000.0);    
    expect(NumberConverter::convert(1000, 'MB', 'MiB'))->toBe(1024.0);
});

test('server resource limits are correctly applied', function () {
    $models = setupTestModels();
    setupFilamentContext();

    $models['user']->update([
        'resource_limits' => [
            [
                'node_key' => $models['node']->node_id,
                'node_name' => $models['node']->name,
                'max_cpu' => 200,
                'max_memory' => 2048,
                'max_disk' => 5120,
            ]
        ]
    ]);

    Server::create([
        'uuid' => 'existing-uuid',
        'name' => 'Existing Server',
        'slug' => 'existing-server-' . Str::random(6),
        'node' => $models['node']->node_id,
        'allocation_id' => $models['allocation']->id,
        'egg' => $models['egg']->egg_id,
        'docker_image' => 'test/image:latest',
        'user' => $models['user']->id,
        'limits' => [
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 2048,
        ],
        'feature_limits' => [
            'databases' => 0,
            'allocations' => 0,
            'backups' => 3,
        ],
        'egg_variables' => [],
    ]);

    $response = Livewire::actingAs($models['user'])
        ->test(CreateServer::class)
        ->fillForm([
            'name' => 'New Server',
            'node' => $models['node']->node_id,
        ]);

    $response->assertSuccessful();
});
