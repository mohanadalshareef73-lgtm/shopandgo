<?php

namespace Database\Seeders;

use App\Models\AiRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AiRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AiRoles::create([
            'role' => 'welcome',
            'message' => 'شكرا على طلبك, انه لمن دواعي سرورنا ان نعمل على توفير الطلب الخاص بكم.',
        ]);
        AiRoles::create([
            'role' => 'note',
            'message' => 'ملاحظة هامة: تم دفع كامل القيمة بعد استشارة المذكور اعلاه',
        ]);
        AiRoles::create([
            'role' => 'note2',
            'message' => 'تم إصدار هذه الفاتورة استناداً إلى طلبكم. نحن سعداء لخدمتكم.',
        ]);

        AiRoles::create([
            'role' => 'order',
            'message' => 'هنا ساشرح لك كيفية حساب الاجماي للزبون بعد ان يرسل بيانات الطلب الخاص به',
        ]);

        AiRoles::create([
            'role' => 'address',
            'message' => 'العنوان / الرحبة شارع قصر عبد إسماعيل 0920703777 - 0920403444',
        ]);


 $permissions = [
            ['name' => 'aiRoles-list', 'guard_name' => 'web', 'ar_name' => 'عرض قواعد الذكاء الاصطناعي' ,'guard_name' => 'web'],
            ['name' => 'aiRoles-create', 'guard_name' => 'web', 'ar_name' => 'انشاء قواعد الذكاء الاصطناعي', 'guard_name' => 'web'],
            ['name' => 'aiRoles-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل قواعد الذكاء الاصطناعي', 'guard_name' => 'web'],
            ['name' => 'aiRoles-delete', 'guard_name' => 'web', 'ar_name' => 'حذف قواعد الذكاء الاصطناعي', 'guard_name' => 'web'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                ['ar_name' => $permission['ar_name']]
            );
        }
        // ✅ Create Admin Role
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        // ✅ Give ALL permissions to Admin
        $adminRole->syncPermissions(Permission::all());


    }
}
