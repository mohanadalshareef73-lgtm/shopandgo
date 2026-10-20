<?php

namespace Database\Seeders;

use App\Models\PurchaseDescription;
use App\Models\ShippingType;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DescriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('purchase_descriptions')->insert([
            ['name' => 'اعلان ممول',
                'created_id' => 1,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ],
            ['name' => 'اكياس',
            'created_id' => 1,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'توصيل',
            'created_id' => 1,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);

        DB::table('shipping_types')->insert([

            ['name' => 'دبي بحري',
            'created_id' => 1,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'دبي جوي',
            'created_id' => 1,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'الصين بحري',
            'created_id' => 1,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'الصين جوي',
            'created_id' => 1,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);
    }
}
