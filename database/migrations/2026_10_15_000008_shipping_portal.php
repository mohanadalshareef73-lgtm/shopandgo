<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - الشحن فقط (الصين / دبي / أمريكا): الشحنة إيصال من نوع shipping
 * - أسعار خاصة للعملاء
 * - بلاغات العملاء (عندي مشكلة) وردود الإدارة
 * - طلب إقفال الحساب من المندوب
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $cols = [
                'kind' => fn () => $t->string('kind', 20)->default('purchase')->index(),
                'ship_origin' => fn () => $t->string('ship_origin', 10)->nullable(),
                'tracking_no' => fn () => $t->string('tracking_no', 80)->nullable()->index(),
                'ship_stage' => fn () => $t->string('ship_stage', 20)->nullable(),
                'usd_per_kg' => fn () => $t->decimal('usd_per_kg', 10, 2)->nullable(),
                'usd_discount' => fn () => $t->decimal('usd_discount', 10, 2)->default(0),
                'usd_amount' => fn () => $t->decimal('usd_amount', 12, 2)->nullable(),
                'usd_rate' => fn () => $t->decimal('usd_rate', 10, 4)->nullable(),
                'last_alert_at' => fn () => $t->timestamp('last_alert_at')->nullable(),
            ];
            foreach ($cols as $c => $fn) {
                if (!Schema::hasColumn('orders', $c)) {
                    $fn();
                }
            }
        });
        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers', 'ship_prices')) {
                $t->text('ship_prices')->nullable(); // {"cn": 9.5, "ae": 5}
            }
        });
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'close_request')) {
                $t->text('close_request')->nullable(); // طلب إقفال الحساب من المندوب (JSON)
            }
        });
        if (Schema::hasTable('trip_purchases') && !Schema::hasColumn('trip_purchases', 'late_alert_at')) {
            Schema::table('trip_purchases', fn (Blueprint $t) => $t->timestamp('late_alert_at')->nullable());
        }
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_id')->index();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->string('type', 40);
                $t->text('message');
                $t->string('image', 255)->nullable();
                $t->string('status', 20)->default('open'); // open | answered | closed
                $t->text('reply')->nullable();
                $t->unsignedBigInteger('replied_by')->nullable();
                $t->timestamp('replied_at')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('permissions')) {
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            $editId = DB::table('permissions')->where('name', 'order-edit')->value('id');
            foreach (['shipping-list' => 'عرض الشحنات', 'shipping-edit' => 'إضافة وتعديل الشحنات', 'ticket-list' => 'بلاغات العملاء'] as $name => $ar) {
                if (!DB::table('permissions')->where('name', $name)->exists()) {
                    $row = ['name' => $name, 'guard_name' => config('auth.defaults.guard', 'web'), 'created_at' => now(), 'updated_at' => now()];
                    if ($hasAr) {
                        $row['ar_name'] = $ar;
                    }
                    $id = DB::table('permissions')->insertGetId($row);
                    if ($editId && Schema::hasTable('role_has_permissions')) {
                        foreach (DB::table('role_has_permissions')->where('permission_id', $editId)->pluck('role_id') as $rid) {
                            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $rid]);
                        }
                    }
                }
            }
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
            }
        }
        if (Schema::hasTable('whatsapp_messages')) {
            $tpls = [
                ['ship_registered', 'تسجيل شحنة', "مرحباً {الاسم}،\nتم تسجيل شحنتك {رقم_الإيصال} من {بلد_الشحن}.\nالوزن: {الوزن} كغ · الشحن: {الشحن_دولار} $ (≈ {الشحن} د.ل)\nShop & Go"],
                ['ship_stage', 'تغيّر حالة الشحنة', "مرحباً {الاسم}،\nحالة شحنتك {رقم_الإيصال}: {الحالة}.\nShop & Go"],
                ['ticket_reply', 'الرد على بلاغ', "مرحباً {الاسم}،\nبخصوص بلاغك: {الرد}\nShop & Go"],
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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('support_tickets');
        $cols = array_values(array_filter(['kind', 'ship_origin', 'tracking_no', 'ship_stage', 'usd_per_kg', 'usd_discount', 'usd_amount', 'usd_rate', 'last_alert_at'], fn ($c) => Schema::hasColumn('orders', $c)));
        if ($cols) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($cols));
        }
        foreach ([['customers', 'ship_prices'], ['users', 'close_request'], ['trip_purchases', 'late_alert_at']] as [$tb, $c]) {
            if (Schema::hasColumn($tb, $c)) {
                Schema::table($tb, fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        Schema::enableForeignKeyConstraints();
    }
};
