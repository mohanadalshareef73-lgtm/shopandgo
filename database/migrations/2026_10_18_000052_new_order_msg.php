<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسالة تسجيل الطلب (إيصال جديد): بدون "ألف مبروك" — "تم تسجيل طلبك في المنظومة" + بياناته + رابط المنظومة. نص الرسالة بس، ما فيش بيانات تتمس */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasColumn('whatsapp_messages', 'type')) {
            return;
        }
        $msg = "✅ *تم تسجيل طلبك في المنظومة* — Shop & Go\n\n"
            . "👤 الاسم: {الاسم}\n🆔 كودك: *{كود_الزبون}*\n📱 رقمك: {الهاتف}\n\n"
            . "🔢 رقم الإيصال: *{رقم_الإيصال}*\n🛍 قيمة الطلب: {الفاتورة} د.ل\n✅ المدفوع: {المدفوع} د.ل\n⏳ الباقي: {الباقي} د.ل\n\n"
            . "🧾 فاتورتك 👇\n{رابط_الفاتورة}\n\n"
            . "💻 حسابك في المنظومة (تتابع منه طلباتك) 👇\n{رابط_الحساب}\n🔑 ادخل برقمك · نسيت كلمة المرور؟ يجيك رمز على الواتساب";
        DB::table('whatsapp_messages')->where('type', 'new')->update(['message' => $msg, 'updated_at' => now()]);
    }

    public function down(): void
    {
    }
};
