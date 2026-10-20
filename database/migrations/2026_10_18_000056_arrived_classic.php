<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسالة «وصلت بضاعتك» بنفس الصيغة القديمة + رابط الدخول للمنظومة واسم المستخدم. نص بس */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasColumn('whatsapp_messages', 'type')) {
            return;
        }
        $msg = "شركة SHOP AND GO للشراء و الشحن\n\n"
            . "حالة الطلب الخاص بك (جاهز للاستلام)\n\n"
            . "اهلا عميلنا العزيز ({الاسم})\n"
            . "رقم الكود الخاص بك: ({كود_الزبون})\n"
            . "رقم الطلب: ({رقم_الإيصال})\n"
            . "رقم الرحلة: ({الرحلة})\n\n"
            . "قيمة الشحن: ({الشحن}) د.ل\n"
            . "الإجمالي المتبقي على الطلب مع الشحن : ({الباقي}) د.ل\n\n"
            . "ملاحظة هامة :\n"
            . "الشركة غير مسؤولة عن طلبك بعد مرور أسبوع إبتداءً من اليوم\n\n"
            . "لمعرفة باقي تفاصيل طلبك ادخل لحسابك في المنظومة 👇\n"
            . "{رابط_الحساب}\n"
            . "📱 اسم المستخدم: رقم هاتفك ({الهاتف})\n\n"
            . "مقر الشركة\n"
            . "بنغازي - الفويهات - الرحبة بجانب النفق\n\n"
            . "شكرآ لإختياركم شركتنا ونتمنى لكم تجربة ممتعة";
        DB::table('whatsapp_messages')->where('type', 'arrived')->update(['message' => $msg, 'updated_at' => now()]);
    }

    public function down(): void
    {
    }
};
