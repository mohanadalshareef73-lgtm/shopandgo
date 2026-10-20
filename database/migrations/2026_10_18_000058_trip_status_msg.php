<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسالة تغيير حالة الرحلة (مثلاً وصل مخزن دبي): الصيغة القديمة + الكود والاسم ورقم الطلب ورابط المنظومة بس. نص بس */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasColumn('whatsapp_messages', 'type')) {
            return;
        }
        $msg = "شركة SHOP AND GO للشراء و الشحن\n\n"
            . "اهلا عميلنا العزيز ({الاسم})\n"
            . "رقم الكود الخاص بك: ({كود_الزبون})\n"
            . "رقم الطلب: ({رقم_الإيصال})\n\n"
            . "📦 حالة طلبك: ({الحالة})\n\n"
            . "لمعرفة باقي تفاصيل طلبك ادخل لحسابك في المنظومة 👇\n"
            . "{رابط_الحساب}";
        DB::table('whatsapp_messages')->where('type', 'trip_status')->update(['message' => $msg, 'updated_at' => now()]);
    }

    public function down(): void
    {
    }
};
