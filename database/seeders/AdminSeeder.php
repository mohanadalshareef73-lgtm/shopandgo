<?php

namespace Database\Seeders;

use App\Models\Org;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'name' => 'المدير',
            'email' => 'admin@shop.ly',
            'password' => bcrypt('TEST2026'),
            'type_id' => 1,
        ]);
        $role = Role::create(['name' => 'Admin']);
        // Fetch all permissions as an array
        $permissions = Permission::all();
        // Assign all permissions to the role
        $role->syncPermissions($permissions);
        // Assign the role to the user
        $user->assignRole($role->name);
        Role::create(['name' => 'مشرف']);

    }
}
