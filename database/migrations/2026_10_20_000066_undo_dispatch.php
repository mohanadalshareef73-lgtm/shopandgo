<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ↩ إلغاء صفحة «طلبيات دليفري»: كل طلبية رجعت مكانها (الرف) · طلبات العملاء رجعت لصفحة المندوبين — ما يمسحش شي */
return new class extends Migration
{
    public function up()
    {
        return; // ↩ اتراجعنا: صفحة طلبيات دليفري رجعت — ما نلغوش شي
        if (!Schema::hasTable('deliveries') || !Schema::hasColumn('deliveries', 'batch')) {
            return;
        }
        $active = ['requested', 'assigned', 'out', 'problem'];
        // 1) اللي اتعينوا من الصفحة (طلعة): الإيصالات ترجع للرف والتوصيل يتلغى
        $ids = DB::table('deliveries')->whereNotNull('batch')->whereIn('status', $active)->pluck('id');
        foreach ($ids as $id) {
            DB::table('orders')->where('delivery_id', $id)->where('delivered', false)->update(['delivery_id' => null]);
            DB::table('deliveries')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            if (Schema::hasTable('delivery_events')) {
                DB::table('delivery_events')->insert(['delivery_id' => $id, 'status' => 'cancelled', 'note' => 'إلغاء طلبيات دليفري: رجعت للرف', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // 2) طلبات العملاء اللي تعطت لمندوب بالاسم من الصفحة: ترجع «طلب ينتظر مندوب» في صفحة المندوبين
        $named = DB::table('deliveries')->whereNull('batch')->whereNull('courier_id')->whereNotNull('courier_name')->whereIn('status', $active)->pluck('id');
        DB::table('deliveries')->whereIn('id', $named)->update(['status' => 'requested', 'courier_name' => null, 'assigned_at' => null, 'updated_at' => now()]);
        // 3) النوع/الطابور يرجعوا فاضيين على المفتوح
        DB::table('deliveries')->whereIn('status', $active)->update(['zone' => null]);
    }

    public function down()
    {
    }
};
