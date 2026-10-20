<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** نتأكدو إن كل صلاحيات الأقسام الجديدة موجودة باش تتعطى للموظفين */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $hasAr = Schema::hasColumn('permissions', 'ar_name');
        $list = [
            'return-list' => 'عرض المرتجعات', 'return-create' => 'تسجيل مرتجع', 'ticket-list' => 'بلاغات العملاء', 'soldout-manage' => 'المنتجات اللي نفدت',
            'shein-card-list' => 'عرض بطاقات SHEIN', 'shein-card-create' => 'إضافة بطاقة SHEIN', 'shein-card-use' => 'استعمال بطاقة SHEIN', 'shein-card-delete' => 'حذف بطاقة SHEIN',
            'shein-card-pin' => 'كشف رمز البطاقة', 'shein-card-sell' => 'بيع بطاقات SHEIN', 'shipping-list' => 'عرض الشحن', 'shipping-edit' => 'تعديل الشحن',
            'courier-list' => 'عرض المندوبين', 'courier-create' => 'إضافة مندوب', 'courier-edit' => 'إسناد وتعديل التوصيل', 'courier-settle' => 'تقفيل المندوب',
            'offer-manage' => 'العروض والكوبونات', 'points-edit' => 'تعديل النقاط',
        ];
        $guard = config('auth.defaults.guard', 'web');
        foreach ($list as $name => $ar) {
            if (!DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->exists()) {
                $row = ['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()];
                if ($hasAr) {
                    $row['ar_name'] = $ar;
                }
                DB::table('permissions')->insert($row);
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
