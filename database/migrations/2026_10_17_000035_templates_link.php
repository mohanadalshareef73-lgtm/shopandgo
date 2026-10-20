<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رابط المنظومة مكتوب داخل كل رسالة للعميل (يبان في صفحة الرسائل وتقدر تعدله) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            return;
        }
        $admin = ['shift_closed', 'monthly_report', 'alert', 'report', 'backup', 'otp'];
        foreach (DB::table('whatsapp_messages')->get() as $t) {
            if (in_array($t->type, $admin, true) || str_contains((string) $t->message, 'shopandgo.ly')) {
                continue;
            }
            $msg = rtrim((string) $t->message) . "\n\n👈 اضغط هنا وشوف كل التفاصيل:\nhttps://shopandgo.ly/login";
            DB::table('whatsapp_messages')->where('id', $t->id)->update(['message' => $msg, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
