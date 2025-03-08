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
    config(['services.turnstile.TURNSTILE_SITE_ENABLE' => false]);
    
    $response = $this->post('/admin/login', [
        'data' => [
            'name' => 'non-existent',
            'password' => 'wrong-password',
            'remember' => false
        ]
    ]);
    $response->assertRedirect('/');
});

test('valid credentials login', function () {
    config(['services.turnstile.TURNSTILE_SITE_ENABLE' => false]);
    
    $permissions = [
        'server.create',
        'server.edit',
        'server.delete',
        'server.view',
        'server.import',
        'node.edit',
        'node.view',
        'egg.edit',
        'egg.create',
        'egg.delete',
        'egg.view',
        'allocation.view',
        'user.create',
        'user.edit',
        'user.delete',
        'user.view',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $user = User::factory()->create([
        'name' => 'testuser',
        'password' => Hash::make('secret'),
    ]);

    $this->actingAs($user, config('filament.auth.guard'));
    $response = $this->post('/admin/login', [
        'name' => 'testuser',
        'password' => 'secret',
        'remember' => true,
    ]);
    $response->assertRedirect('/');
    $this->assertAuthenticated(config('filament.auth.guard'));
});

test('2fa login requires verification code', function () {
    config(['services.turnstile.TURNSTILE_SITE_ENABLE' => false]);
    User::factory()->create([
        'name' => 'testuser',
        'password' => Hash::make('secret'),
        'google2fa_enabled' => true,
        'google2fa_secret' => 'TESTSECRET',
    ]);
    
    $response = $this->post('/admin/login', [
        'data' => [
            'name' => 'testuser',
            'password' => 'secret',
            'remember' => false
        ]
    ]);
    $response->assertRedirect('/');
    $this->assertGuest();
});
