<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسالة «تسجيل الطلب» بنفس بيانات الصيغة القديمة بأسلوب أوضح + رقم الطلب والرابط (والإيصال PDF يجي معاها). نص بس */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasColumn('whatsapp_messages', 'type')) {
            return;
        }
        $msg = "شركة SHOP AND GO للشراء و الشحن 🤍\n\n"
            . "اهلا عميلنا العزيز ({الاسم})\n"
            . "رقم الكود الخاص بك: ({كود_الزبون})\n"
            . "رقم الطلب: ({رقم_الإيصال})\n\n"
            . "✅ تم تسجيل طلبك بنجاح في منظومة التتبع\n"
            . "🕒 الوقت المتوقع لتسليم الطلب 20 يوم كحد أقصى من تاريخ الطلب\n"
            . "🧾 إيصالك مرفق في الملف\n\n"
            . "تابع طلبك خطوة بخطوة من حسابك في المنظومة 👇\n"
            . "{رابط_الحساب}\n"
            . "📱 اسم المستخدم: رقم هاتفك\n"
            . "🔑 كلمة المرور: أول مرة رقم هاتفك، وبعدها تختار كلمة مرور جديدة\n\n"
            . "📍 مقر الشركة: بنغازي - الفويهات - الرحبة بجانب النفق\n\n"
            . "شكرآ لإختياركم شركتنا ونتمنى لكم تجربة ممتعة 💙";
        DB::table('whatsapp_messages')->where('type', 'new')->update(['message' => $msg, 'updated_at' => now()]);
    }

    public function down(): void
    {
    }
};
