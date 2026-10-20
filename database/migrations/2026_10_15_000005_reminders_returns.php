<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - تذكير الرف الأوتوماتيكي: orders.shelf_reminded_at / shelf_reminders
 * - تذكير الديون بتأكيد: customers.debt_reminded_at
 * - المرتجع والاستبدال: order_returns
 * - صلاحيات المرتجع
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'shelf_reminded_at')) {
                $t->timestamp('shelf_reminded_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shelf_reminders')) {
                $t->unsignedSmallInteger('shelf_reminders')->default(0);
            }
            if (!Schema::hasColumn('orders', 'returned_value')) {
                $t->decimal('returned_value', 12, 2)->default(0);
            }
        });
        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers', 'debt_reminded_at')) {
                $t->timestamp('debt_reminded_at')->nullable();
            }
        });

        if (!Schema::hasTable('order_returns')) {
            Schema::create('order_returns', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('order_id')->index();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->string('type', 20)->default('return');      // return | exchange
                $t->unsignedInteger('qty')->default(0);          // عدد القطع المرجعة
                $t->string('items', 500)->nullable();            // شن رجع
                $t->decimal('amount', 12, 2)->default(0);        // قيمة اللي رجع (للزبون)
                $t->decimal('extra', 12, 2)->default(0);         // الاستبدال: الزبون يزيد (فرق سعر)
                $t->string('method', 20)->default('deduct');    // deduct | cash | credit | none
                $t->unsignedBigInteger('payment_id')->nullable();
                $t->decimal('refunded', 12, 2)->default(0);      // اللي طلع فعلا من الخزينة
                $t->string('goods', 20)->default('shop');        // shop (عندنا) | supplier (رجعت للمورد) | damaged
                $t->string('reason', 300)->nullable();
                $t->string('new_items', 500)->nullable();        // الاستبدال: شن خذا بدالها
                $t->unsignedBigInteger('created_id')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('permissions')) {
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            foreach (['return-list' => 'عرض المرتجعات', 'return-create' => 'تسجيل مرتجع / استبدال'] as $name => $ar) {
                if (!DB::table('permissions')->where('name', $name)->exists()) {
                    $row = ['name' => $name, 'guard_name' => config('auth.defaults.guard', 'web'), 'created_at' => now(), 'updated_at' => now()];
                    if ($hasAr) {
                        $row['ar_name'] = $ar;
                    }
                    $id = DB::table('permissions')->insertGetId($row);
                    // نعطيها لكل دور عنده order-edit
                    $editId = DB::table('permissions')->where('name', 'order-edit')->value('id');
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

        // قالب واتساب: استلمنا المرتجع
        if (Schema::hasTable('whatsapp_messages') && !DB::table('whatsapp_messages')->where('type', 'return_done')->exists()) {
            $row = ['type' => 'return_done', 'message' => "مرحبا {الاسم} 👋\nسجلنا المرتجع على الإيصال {رقم_الإيصال}.\n{المرتجع}\nShop & Go", 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('whatsapp_messages', 'title')) {
                $row['title'] = 'تسجيل مرتجع';
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
        Schema::dropIfExists('order_returns');
        $cols = array_values(array_filter(['shelf_reminded_at', 'shelf_reminders', 'returned_value'], fn ($c) => Schema::hasColumn('orders', $c)));
        if ($cols) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($cols));
        }
        Schema::table('customers', function (Blueprint $t) {
            if (Schema::hasColumn('customers', 'debt_reminded_at')) {
                $t->dropColumn('debt_reminded_at');
            }
        });
        Schema::enableForeignKeyConstraints();
    }
};
