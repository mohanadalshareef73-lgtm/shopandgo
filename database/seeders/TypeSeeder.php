<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Platform;
use App\Models\Type;
use Carbon\Carbon;
use Faker\Provider\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
                    DB::table('types')->insert([

            [
                'type'=>'مدير النظام',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'type'=>'موظف',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'type'=>'زبون',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'type'=>'مندوب توصيل',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
        ]);

        Platform::insert([
            [
                'name'=>'Alibaba',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],

            [
                'name'=>'shein',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'name'=>'amazon',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'name'=>'aliexpress',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
        ]);

        PaymentMethod::insert([
            [
                'name'=>'نقداً',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],

            [
                'name'=>'تحويل بنكي',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            [
                'name'=>'بطاقة مصرفية',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
        ]);

    }
}
