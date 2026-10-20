<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** منتجات نفدت أثناء الشراء: إبلاغ العميل بها وبسعرها، ثم إلغاء (خصم) أو استبدال (إيصال جديد) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_soldouts')) {
            Schema::create('order_soldouts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('order_id')->index();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->string('item', 200);
                $t->unsignedInteger('qty')->default(1);
                $t->decimal('price', 12, 2)->default(0);           // قيمة الكمية كاملة
                $t->string('status', 20)->default('pending');        // pending · cancelled · replaced
                $t->string('method', 20)->nullable();                // deduct · credit · cash (للمبلغ الزائد)
                $t->decimal('refunded', 12, 2)->default(0);
                $t->decimal('credited', 12, 2)->default(0);
                $t->string('replacement', 200)->nullable();
                $t->decimal('replacement_price', 12, 2)->nullable();
                $t->unsignedBigInteger('new_order_id')->nullable();
                $t->timestamp('notified_at')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->unsignedBigInteger('created_id')->nullable();
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->string('note', 300)->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('permissions')) {
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            $editId = DB::table('permissions')->where('name', 'order-edit')->value('id');
            if (!DB::table('permissions')->where('name', 'soldout-manage')->exists()) {
                $row = ['name' => 'soldout-manage', 'guard_name' => config('auth.defaults.guard', 'web'), 'created_at' => now(), 'updated_at' => now()];
                if ($hasAr) {
                    $row['ar_name'] = 'المنتجات النافدة (إلغاء/استبدال)';
                }
                $id = DB::table('permissions')->insertGetId($row);
                if ($editId && Schema::hasTable('role_has_permissions')) {
                    foreach (DB::table('role_has_permissions')->where('permission_id', $editId)->pluck('role_id') as $rid) {
                        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $rid]);
                    }
                }
            }
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
            }
        }
        if (Schema::hasTable('whatsapp_messages') && !DB::table('whatsapp_messages')->where('type', 'soldout_notice')->exists()) {
            $row = ['type' => 'soldout_notice', 'message' => "مرحباً {الاسم} 👋\nأثناء شراء طلبك رقم {رقم_الإيصال} وجدنا أن المنتجات التالية نفدت من المتجر:\n\n{المنتجات}\n\nالمجموع: {المجموع} د.ل\n\nيرجى الرد على هذه الرسالة:\n1️⃣ إلغاؤها وخصم قيمتها من طلبك\n2️⃣ أو استبدالها بمنتج آخر (أرسل لنا الرابط)\n\nShop & Go", 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('whatsapp_messages', 'title')) {
                $row['title'] = 'منتجات نفدت';
            }
            if (Schema::hasColumn('whatsapp_messages', 'enabled')) {
                $row['enabled'] = true;
            }
            DB::table('whatsapp_messages')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_soldouts');
    }
};
