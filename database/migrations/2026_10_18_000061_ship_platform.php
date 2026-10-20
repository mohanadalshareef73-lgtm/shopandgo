<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** الشحن: المنصة اللي شرى منها العميل + قالب رسالة «وصل طلبك» (ship_arrived) — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'ship_platform')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->string('ship_platform', 80)->nullable();
            });
        }
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'ship_cur')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->string('ship_cur', 4)->nullable(); // عملة سعر الشحن اللي انكتب: usd / lyd
            });
        }
        if (Schema::hasTable('whatsapp_messages') && Schema::hasColumn('whatsapp_messages', 'type')
            && !DB::table('whatsapp_messages')->where('type', 'ship_arrived')->exists()) {
            $r = ['type' => 'ship_arrived', 'message' => \App\Services\ShippingService::ARRIVED_MSG, 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('whatsapp_messages', 'title')) {
                $r['title'] = 'شحنة جاهزة للاستلام';
            }
            DB::table('whatsapp_messages')->insert($r);
        }
    }

    public function down()
    {
    }
};
