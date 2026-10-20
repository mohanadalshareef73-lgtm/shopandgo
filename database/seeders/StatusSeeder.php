<?php

namespace Database\Seeders;

use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
            DB::table('statuses')->insert([
            [
                'name'=>'قيد التجهيز',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'name'=>'وصلت مخزن دبي',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'name'=>'جاهز للاستلام',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
        ]);

        // Permission::insert([
        //     ['name' => 'status-list', 'guard_name' => 'web', 'ar_name' => 'عرض حالات الرحلة'],
        //     ['name' => 'status-create', 'guard_name' => 'web', 'ar_name' => 'انشاء حالة رحلة'],
        //     ['name' => 'status-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل حالة الرحلة'],
        //     ['name' => 'status-delete', 'guard_name' => 'web', 'ar_name' => 'حذف حالة الرحلة'],
        // ]);



    }
}
