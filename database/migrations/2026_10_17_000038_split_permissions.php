<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** صلاحيات مفصّلة (ترى/تضيف/تعدّل/تحذف) للأقسام اللي كانت ناقصة. اللي عنده الصلاحية القديمة ياخذ الجديدة أوتوماتيك */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $hasAr = Schema::hasColumn('permissions', 'ar_name');
        $guard = config('auth.defaults.guard', 'web');
        // الجديدة => [الاسم بالعربي، الصلاحية القديمة اللي كانت تغطيها]
        $list = [
            'delivery-undo' => ['التراجع عن تسليم', 'delivery-create'],
            'trip-purchase-edit' => ['تعديل رحلة وإضافة/إخراج إيصالات', 'trip-purchase-create'],
            'shelf-list' => ['عرض الرفوف والجرد', 'delivery-list'],
            'shelf-edit' => ['جرد الرفوف والتذكير', 'delivery-list'],
            'shipping-create' => ['إضافة شحنة', 'shipping-edit'],
        ];
        foreach ($list as $name => [$ar, $old]) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id');
            if (!$id) {
                $row = ['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()];
                if ($hasAr) {
                    $row['ar_name'] = $ar;
                }
                $id = DB::table('permissions')->insertGetId($row);
            }
            $oldId = DB::table('permissions')->where('name', $old)->where('guard_name', $guard)->value('id');
            if (!$oldId) {
                continue;
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $oldId)->get() as $m) {
                DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $id, 'model_type' => $m->model_type, 'model_id' => $m->model_id]);
            }
            foreach (DB::table('role_has_permissions')->where('permission_id', $oldId)->pluck('role_id') as $rid) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $rid]);
            }
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
    }
};
