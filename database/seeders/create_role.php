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
            'server.create',
            'server.edit',
            'server.delete',
            'server.view',
            'server.import',
            'node.edit',
            'node.create',
            'node.view',
            'egg.edit',
            'egg.delete',
            'egg.view',
            'allocation.view',
            'user.create',
            'user.edit',
            'user.delete',
            'user.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);

        $user = Role::firstOrCreate(['name' => 'user']);

        $admin->syncPermissions(Permission::all());

        $user->syncPermissions(['server.view', 'server.create', 'server.delete']);
    }
}
