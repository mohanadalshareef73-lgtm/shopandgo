<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسالتين قصار: «تسجيل الطلب» (رقم الطلب + رابط المنظومة، والإيصال PDF معاها) و«وصلت بضاعتك» (رقم الطلب + الرحلة + كم عليه شامل الشحن + الرابط). نص بس */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasColumn('whatsapp_messages', 'type')) {
            return;
        }
        DB::table('whatsapp_messages')->where('type', 'new')->update(['updated_at' => now(), 'message' =>
            "✅ *تم تسجيل طلبك*\n\n🔢 رقم الطلب: *{رقم_الإيصال}*\n🧾 الإيصال في الملف 👆\n\n👇 تابع طلبك من حسابك في المنظومة\n{رابط_الحساب}"]);
        DB::table('whatsapp_messages')->where('type', 'arrived')->update(['updated_at' => now(), 'message' =>
            "🎉 *وصلت بضاعتك يا {الاسم}!*\n\n🔢 رقم الطلب: *{رقم_الإيصال}*\n✈️ رقم الرحلة: *{الرحلة}*\n💵 *عليك (شامل الشحن): {الباقي} د.ل*\n\n👇 كل التفاصيل في حسابك\n{رابط_الحساب}"]);
    }

    public function down(): void
    {
    }
};
