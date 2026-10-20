<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TreasurySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $payments = PaymentMethod::all();
        foreach($payments as $payment){
            TreasuryDollar::create([
                'total' => 0,
                'payment_id' => $payment->id,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ]);
            TreasuryEuro::create([
                'total' => 0,
                'payment_id' => $payment->id,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
            ]);
            if($payment->id != 1){
                Treasury::create([
                    'total' => 0,
                   'payment_id' => $payment->id,
                    'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
                ]);
            }
        }
    }
}
