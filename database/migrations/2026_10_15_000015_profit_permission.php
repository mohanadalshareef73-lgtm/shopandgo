<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** صلاحية رؤية الأرباح والتكاليف: للمدير فقط (لا تظهر للموظفين إلا إذا منحها المدير) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || DB::table('permissions')->where('name', 'profit-view')->exists()) {
            return;
        }
        $row = ['name' => 'profit-view', 'guard_name' => config('auth.defaults.guard', 'web'), 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('permissions', 'ar_name')) {
            $row['ar_name'] = 'رؤية الأرباح والتكاليف';
        }
        $id = DB::table('permissions')->insertGetId($row);
        $admin = DB::table('roles')->where('name', 'Admin')->value('id');
        if ($admin && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $admin]);
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'profit-view')->delete();
    }
};
