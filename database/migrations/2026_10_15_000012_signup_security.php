<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** تسجيل العملاء بأنفسهم (بموافقة الإدارة) + غرض شراء الدولار */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers', 'signup_status')) {
                $t->string('signup_status', 20)->nullable()->index(); // null = معتمد · pending · rejected
                $t->timestamp('signup_at')->nullable();
                $t->timestamp('phone_verified_at')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->string('signup_note', 300)->nullable();
                $t->string('signup_ip', 45)->nullable();
            }
        });
        if (Schema::hasTable('currency_exchanges') && !Schema::hasColumn('currency_exchanges', 'purpose')) {
            Schema::table('currency_exchanges', fn (Blueprint $t) => $t->string('purpose', 20)->nullable());
        }
        if (Schema::hasTable('whatsapp_messages')) {
            $tpls = [
                ['account_approved', 'الموافقة على حساب جديد', "مرحباً {الاسم} 👋\nتمت الموافقة على حسابك في Shop & Go ✅\nكود العميل الخاص بك: *{كود_الزبون}*\n\nيمكنك الدخول برقم هاتفك وكلمة المرور التي اخترتها:\n{رابط_الموقع}/login"],
                ['account_rejected', 'رفض طلب تسجيل', "مرحباً {الاسم}\nنعتذر، لم تتم الموافقة على طلب التسجيل حالياً.\n{السبب}\nللاستفسار تواصل معنا."],
            ];
            foreach ($tpls as [$type, $title, $msg]) {
                if (!DB::table('whatsapp_messages')->where('type', $type)->exists()) {
                    $row = ['type' => $type, 'message' => $msg, 'created_at' => now(), 'updated_at' => now()];
                    if (Schema::hasColumn('whatsapp_messages', 'title')) {
                        $row['title'] = $title;
                    }
                    if (Schema::hasColumn('whatsapp_messages', 'enabled')) {
                        $row['enabled'] = true;
                    }
                    DB::table('whatsapp_messages')->insert($row);
                }
            }
        }
    }

    public function down(): void
    {
        $cols = array_values(array_filter(['signup_status', 'signup_at', 'phone_verified_at', 'approved_by', 'approved_at', 'signup_note', 'signup_ip'], fn ($c) => Schema::hasColumn('customers', $c)));
        if ($cols) {
            Schema::disableForeignKeyConstraints();
            Schema::table('customers', fn (Blueprint $t) => $t->dropColumn($cols));
            Schema::enableForeignKeyConstraints();
        }
    }
};
