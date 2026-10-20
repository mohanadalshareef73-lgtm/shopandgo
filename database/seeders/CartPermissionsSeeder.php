<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CartPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [];
        foreach (['cart-list' => 'عرض سلات شي إن', 'cart-edit' => 'متابعة وتعديل حالة سلات شي إن', 'cart-create' => 'فحص وإضافة سلة شي إن'] as $name => $label) {
            $permissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['ar_name' => $label]);
        }
        // Preserve the existing administrator permissions; add only cart permissions.
        Role::where('name', 'Admin')->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
