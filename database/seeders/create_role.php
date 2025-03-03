<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class create_role extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);

        $user = Role::firstOrCreate(['name' => 'user']);

        $admin->syncPermissions(Permission::all());

        $user->syncPermissions(['servers.view']);
    }
}
