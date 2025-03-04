<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('login page is accessible', function () {
    $response = $this->get('/admin/login');
    $response->assertStatus(200);
});

test('invalid credentials fail to login', function () {
    config(['services.turnstile.secret' => '']);
    $response = $this->post('/admin/login', [
        'name' => 'non-existent',
        'password' => 'wrong-password',
    ]);
    $response->assertRedirect('/admin/login');
});

test('valid credentials login', function () {
    config(['services.turnstile.secret' => '']);
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

    $user = User::factory()->create([
        'name' => 'testuser',
        'password' => Hash::make('secret'),
    ]);

    $response = $this->post('/admin/login', [
        'name'     => 'testuser',
        'password' => 'secret',
    ]);
    $response->assertRedirect();
    $this->assertAuthenticatedAs($user, config('filament.auth.guard'));
});
