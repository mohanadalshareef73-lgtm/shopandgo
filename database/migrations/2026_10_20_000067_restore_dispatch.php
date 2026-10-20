<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ↩↩ رجوع طلبيات دليفري: اللي اتلغوا بملف الإلغاء يرجعوا مع المندوب زي ما كانوا (لو الإيصالات مازالت في الرف) */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('delivery_events') || !Schema::hasColumn('deliveries', 'batch')) {
            return;
        }
        $ids = DB::table('delivery_events')->where('note', 'إلغاء طلبيات دليفري: رجعت للرف')->pluck('delivery_id')->unique();
        foreach ($ids as $id) {
            $dl = DB::table('deliveries')->where('id', $id)->where('status', 'cancelled')->first();
            if (!$dl) {
                continue;
            }
            $orderIds = DB::table('delivery_order')->where('delivery_id', $id)->pluck('order_id');
            $free = DB::table('orders')->whereIn('id', $orderIds)->where('delivered', false)->whereNull('delivery_id')->pluck('id');
            if ($orderIds->count() && $free->isEmpty()) {
                continue; // الإيصالات تعطت لتوصيل ثاني أو تسلمت
            }
            DB::table('orders')->whereIn('id', $free)->update(['delivery_id' => $id]);
            DB::table('deliveries')->where('id', $id)->update(['status' => $dl->courier_id ? 'assigned' : 'out', 'updated_at' => now()]);
            DB::table('delivery_events')->insert(['delivery_id' => $id, 'status' => $dl->courier_id ? 'assigned' : 'out', 'note' => 'رجعت لطلبيات دليفري', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down()
    {
    }
};
