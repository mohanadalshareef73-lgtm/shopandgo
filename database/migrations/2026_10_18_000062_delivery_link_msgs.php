<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسائل «جاهز للاستلام»: سطر «اطلب مندوب» + رابطه تحت الرسالة · سعر الشحن بالعملة المختارة. نص بس */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages') || !Schema::hasColumn('whatsapp_messages', 'type')) {
            return;
        }
        $line = "🛵 تبي نوصلوهولك لباب بيتك؟ اطلب مندوب من هني 👇\n{رابط_التوصيل}";
        foreach (['ship_arrived', 'arrived'] as $type) {
            $row = DB::table('whatsapp_messages')->where('type', $type)->first();
            if (!$row || str_contains((string) $row->message, '{رابط_التوصيل}')) {
                continue;
            }
            $msg = (string) $row->message;
            $pos = mb_strpos($msg, '📍 مقر الشركة');
            $msg = $pos !== false ? rtrim(mb_substr($msg, 0, $pos)) . "\n\n" . $line . "\n\n" . mb_substr($msg, $pos) : rtrim($msg) . "\n\n" . $line;
            DB::table('whatsapp_messages')->where('type', $type)->update(['message' => $msg, 'updated_at' => now()]);
        }
        $reg = DB::table('whatsapp_messages')->where('type', 'ship_registered')->first();
        if ($reg && str_contains((string) $reg->message, '{الشحن_دولار}$ (≈ {الشحن} د.ل)')) {
            DB::table('whatsapp_messages')->where('type', 'ship_registered')->update(['message' => str_replace('{الشحن_دولار}$ (≈ {الشحن} د.ل)', '{سعر_الشحن}', $reg->message), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
