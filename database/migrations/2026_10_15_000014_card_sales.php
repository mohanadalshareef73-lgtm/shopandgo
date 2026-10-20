<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** بيع بطاقات SHEIN للعملاء عبر إيصال */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('shein_cards') && !Schema::hasColumn('shein_cards', 'sold_order_id')) {
            Schema::table('shein_cards', function (Blueprint $t) {
                $t->unsignedBigInteger('sold_order_id')->nullable()->index();
                $t->unsignedBigInteger('sold_customer_id')->nullable()->index();
                $t->decimal('sale_price', 12, 2)->nullable();      // سعر البيع بالدينار
                $t->decimal('sale_usd', 12, 2)->nullable();        // ما يعادله بالدولار
                $t->decimal('sale_rate', 10, 4)->nullable();       // سعر الدولار وقت البيع
                $t->timestamp('sold_at')->nullable();
                $t->unsignedBigInteger('sold_by')->nullable();
                $t->timestamp('sent_at')->nullable();
            });
        }
        if (Schema::hasTable('permissions')) {
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            $src = DB::table('permissions')->where('name', 'shein-card-use')->value('id');
            if (!DB::table('permissions')->where('name', 'shein-card-sell')->exists()) {
                $row = ['name' => 'shein-card-sell', 'guard_name' => config('auth.defaults.guard', 'web'), 'created_at' => now(), 'updated_at' => now()];
                if ($hasAr) {
                    $row['ar_name'] = 'بيع بطاقات SHEIN للعملاء';
                }
                $id = DB::table('permissions')->insertGetId($row);
                if ($src && Schema::hasTable('role_has_permissions')) {
                    foreach (DB::table('role_has_permissions')->where('permission_id', $src)->pluck('role_id') as $rid) {
                        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $rid]);
                    }
                }
            }
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
            }
        }
        if (Schema::hasTable('whatsapp_messages') && !DB::table('whatsapp_messages')->where('type', 'card_sold')->exists()) {
            $row = ['type' => 'card_sold', 'message' => "مرحباً {الاسم} 🛍\nهذه بطاقة SHEIN الخاصة بك (إيصال {رقم_الإيصال}):\n\n{البطاقات}\n\nللاستخدام: SHEIN ← حسابي ← المحفظة ← إضافة بطاقة هدية.\n⚠ احتفظ بالرقم والرمز ولا تشاركهما مع أحد.\nShop & Go", 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('whatsapp_messages', 'title')) {
                $row['title'] = 'بيع بطاقة SHEIN';
            }
            if (Schema::hasColumn('whatsapp_messages', 'enabled')) {
                $row['enabled'] = true;
            }
            DB::table('whatsapp_messages')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        $cols = array_values(array_filter(['sold_order_id', 'sold_customer_id', 'sale_price', 'sale_usd', 'sale_rate', 'sold_at', 'sold_by', 'sent_at'], fn ($c) => Schema::hasColumn('shein_cards', $c)));
        if ($cols) {
            Schema::table('shein_cards', fn (Blueprint $t) => $t->dropColumn($cols));
        }
        Schema::enableForeignKeyConstraints();
    }
};
