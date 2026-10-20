<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LastSeeder extends Seeder
{


    public function run()
    {
        $permissions = [
            ['name' => 'profits-list', 'guard_name' => 'web', 'ar_name' => 'عرض الأرباح' ,'guard_name' => 'web'],
            ['name' => 'profits-create', 'guard_name' => 'web', 'ar_name' => 'انشاء أرباح', 'guard_name' => 'web'],
            ['name' => 'profits-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل أرباح', 'guard_name' => 'web'],
            ['name' => 'profits-delete', 'guard_name' => 'web', 'ar_name' => 'حذف أرباح', 'guard_name' => 'web'],
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
        // ✅ Create Supervisor Role
        Role::firstOrCreate(['name' => 'مشرف']);

        $payments = PaymentMethod::all();
        foreach($payments as $payment){
            TreasuryDollar::create([
                'total' => 0,
                'payment_id' => $payment->id,
            ]);
            TreasuryEuro::create([
                'total' => 0,
                'payment_id' => $payment->id,
            ]);
            if($payment->id != 1){
                Treasury::create([
                    'total' => 0,
                   'payment_id' => $payment->id,
                ]);
            }
        }


    }

}
