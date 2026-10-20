<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** إخفاء رقم المندوب عن العملاء (لكل مندوب) + صورة إثبات التسليم + رسالة "المندوب طلع" */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'courier_hide_phone')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('courier_hide_phone')->default(false));
        }
        if (!Schema::hasColumn('deliveries', 'proof_path')) {
            Schema::table('deliveries', fn (Blueprint $t) => $t->string('proof_path', 255)->nullable());
        }
        if (Schema::hasTable('whatsapp_messages') && !DB::table('whatsapp_messages')->where('type', 'courier_out')->exists()) {
            $row = ['type' => 'courier_out', 'message' => "🛵 المندوب طلع بطلبك يا {الاسم}!\nطلب *{رقم_الإيصال}* في الطريق ليك توا، وبيكلمك قبل ما يوصل 📱\nخلّي تليفونك قريب 😊\n\n👈 اضغط هنا وشوف كل التفاصيل:\nhttps://shopandgo.ly/login", 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('whatsapp_messages', 'title')) {
                $row['title'] = 'المندوب طلع بالطلبية';
            }
            DB::table('whatsapp_messages')->insert($row);
        }
    }

    public function down(): void
    {
    }
};
