<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 🛵 الدليفري يرجع من الرابط · بياناته تمشي لقروب «دليفري» · بدون مناديب */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('deliveries')) {
            Schema::table('deliveries', function (Blueprint $t) {
                if (!Schema::hasColumn('deliveries', 'area')) {
                    $t->string('area', 120)->nullable();
                }
                if (!Schema::hasColumn('deliveries', 'group_sent_at')) {
                    $t->timestamp('group_sent_at')->nullable();
                }
            });
        }
        // نص الرسائل: «اطلب مندوب» ← «اطلب توصيل»
        try {
            if (Schema::hasTable('whatsapp_messages')) {
                foreach (\Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('message', 'like', '%اطلب مندوب من هني%')->get(['id', 'message']) as $r) {
                    \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('id', $r->id)->update(['message' => str_replace('اطلب مندوب من هني', 'اطلب توصيل من هني', $r->message), 'updated_at' => now()]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            \App\Services\AppSettings::put(['portal_delivery_enabled' => true, 'couriers_enabled' => false, 'courier_auto_assign' => false]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
    }
};
