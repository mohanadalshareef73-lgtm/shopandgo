<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسالة «وصلت بضاعتك»: كم عليه شامل الشحن + رقم الرحلة + بيانات الدخول (أول مرة برمز واتساب). نص الرسالة بس */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasColumn('whatsapp_messages', 'type')) {
            return;
        }
        $msg = "🎉 *وصلت بضاعتك يا {الاسم}!*\n\n"
            . "🔢 الطلب: *{رقم_الإيصال}*\n✈️ الرحلة: *{الرحلة}*\n📦 القطع: {القطع_الواصلة} · الطرود: {الطرود}\n\n"
            . "🛍 قيمة الطلب: {الفاتورة} د.ل\n🚚 الشحن: {الشحن} د.ل\n✅ المدفوع: {المدفوع} د.ل\n💵 *المطلوب منك (شامل الشحن): {الباقي} د.ل*\n\n"
            . "{دخول_الحساب}";
        DB::table('whatsapp_messages')->where('type', 'arrived')->update(['message' => $msg, 'updated_at' => now()]);
    }

    public function down(): void
    {
    }
};
