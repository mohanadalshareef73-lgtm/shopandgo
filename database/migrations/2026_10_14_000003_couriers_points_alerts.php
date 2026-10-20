<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المندوبين والتوصيل · نقاط الزبائن · التنبيهات · ملاحظات للزبون · تخفيض وقت التسليم
 */
return new class extends Migration
{
    public function up()
    {
        // نوع مستخدم جديد: مندوب توصيل (id = 4). عمود النوع كان enum، نخلوه نص عادي
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE types MODIFY `type` VARCHAR(100) NOT NULL COMMENT 'النوع'");
        } elseif (DB::getDriverName() === 'sqlite') {
            self::sqliteDropEnum('types', 'type');
            self::sqliteDropEnum('treasury_histories', 'type');
            self::sqliteDropEnum('treasury_histories', 'status');
        }
        // (في التركيب الجديد الأنواع تتضاف من TypeSeeder)
        if (DB::table('types')->count() && !DB::table('types')->where('id', 4)->exists()) {
            DB::table('types')->insert(['id' => 4, 'type' => 'مندوب توصيل', 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'courier_perms')) {
                $table->text('courier_perms')->nullable()->comment('صلاحيات المندوب JSON');
            }
            if (!Schema::hasColumn('users', 'courier_zone')) {
                $table->string('courier_zone')->nullable();
            }
        });

        // التوصيل (للمندوب) وطلبات التوصيل من الزبون
        if (!Schema::hasTable('deliveries')) {
            Schema::create('deliveries', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source', 20)->default('admin')->comment('admin | customer');
                $table->string('status', 20)->default('assigned')->comment('requested | assigned | out | delivered | problem | returned | cancelled');
                $table->string('recipient_name')->nullable();
                $table->string('recipient_phone', 30)->nullable();
                $table->string('recipient_phone2', 30)->nullable();
                $table->string('city')->nullable();
                $table->string('address')->nullable();
                $table->string('location_url', 500)->nullable();
                $table->string('package_type')->nullable();
                $table->integer('parcels')->default(1);
                $table->double('amount')->default(0)->comment('المطلوب يتقبض من الزبون (بدون أجرة التوصيل)');
                $table->double('fee')->default(0)->comment('أجرة التوصيل يقبضها المندوب لنفسه');
                $table->string('pay_type', 20)->default('cash')->comment('cash | bank | mixed | paid');
                $table->double('plan_cash')->default(0)->comment('الزبون قال بيدفع كاش');
                $table->double('plan_bank')->default(0)->comment('الزبون قال بيحول مصرف');
                $table->string('bank_receipt')->nullable()->comment('صورة إيصال التحويل');
                $table->foreignId('bank_payment_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->text('note')->nullable()->comment('ملاحظات التوصيل');
                $table->text('problem')->nullable();
                $table->double('collected_cash')->default(0);
                $table->double('collected_bank')->default(0);
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('out_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->unsignedBigInteger('settlement_id')->nullable();
                $table->foreignId('created_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['courier_id', 'status']);
            });
        }
        if (!Schema::hasTable('delivery_order')) {
            Schema::create('delivery_order', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->double('due')->default(0)->comment('الباقي على الإيصال وقت التعيين');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('delivery_events')) {
            Schema::create('delivery_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('courier_settlements')) {
            Schema::create('courier_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
                $table->integer('deliveries_count')->default(0);
                $table->double('collected_cash')->default(0);
                $table->double('fees')->default(0);
                $table->double('expected')->default(0)->comment('المفروض يسلّم = الكاش − أجرته');
                $table->double('received')->default(0);
                $table->double('rounding')->default(0)->comment('كسور تلغت');
                $table->double('diff')->default(0)->comment('باقي عليه (+) أو زيادة (−)');
                $table->foreignId('payment_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->text('note')->nullable();
                $table->foreignId('created_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // التنبيهات اللحظية (المندوب سلّم / في مشكلة / زبون طلب توصيل)
        if (!Schema::hasTable('sg_alerts')) {
            Schema::create('sg_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('type', 30);
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('url', 500)->nullable();
                $table->string('level', 10)->default('info')->comment('info | ok | warn');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // النقاط
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'points')) {
                $table->integer('points')->default(0);
            }
            if (!Schema::hasColumn('customers', 'location_url')) {
                $table->string('location_url', 500)->nullable();
            }
        });
        if (!Schema::hasTable('points_history')) {
            Schema::create('points_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->integer('points');
                $table->string('reason');
                $table->foreignId('created_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // الإيصال: تخفيض وقت التسليم + نقاط مستعملة + ربطه بالتوصيل
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_discount')) {
                $table->double('delivery_discount')->default(0);
            }
            if (!Schema::hasColumn('orders', 'points_used')) {
                $table->integer('points_used')->default(0);
            }
            if (!Schema::hasColumn('orders', 'points_earned')) {
                $table->integer('points_earned')->default(0);
            }
            if (!Schema::hasColumn('orders', 'delivery_id')) {
                $table->unsignedBigInteger('delivery_id')->nullable()->index();
            }
            if (!Schema::hasColumn('orders', 'delivered_qty')) {
                $table->integer('delivered_qty')->nullable();
            }
        });

        // ملاحظة تبان للزبون (في حسابه وتنبعت واتساب)
        Schema::table('order_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('order_notes', 'visible')) {
                $table->boolean('visible')->default(false);
            }
            if (!Schema::hasColumn('order_notes', 'sent')) {
                $table->boolean('sent')->default(false);
            }
        });
        if (!Schema::hasTable('customer_messages')) {
            Schema::create('customer_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->text('body');
                $table->boolean('sent')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->foreignId('created_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // قوالب واتساب جديدة
        $tpls = [
            ['courier_assigned', 'طلبك مع المندوب', "اهلا {الاسم}\nطلبك {رقم_الإيصال} طلع مع المندوب {المندوب} ({تليفون_المندوب}).\nالمطلوب: {الباقي} د.ل\nبيتصل بيك قبل ما يوصل 🚚"],
            ['courier_delivered', 'وصلك الطلب (مندوب)', "اهلا {الاسم}\nتم توصيل طلبك {رقم_الإيصال} ✅\nشكراً لاختيارك Shop & Go"],
            ['delivery_request', 'استلمنا طلب التوصيل', "اهلا {الاسم}\nاستلمنا طلب التوصيل لـ {رقم_الإيصال}، بنعيّن مندوب ونكلموك."],
            ['customer_note', 'ملاحظة للزبون', "اهلا {الاسم}\n{الملاحظة}"],
            ['points_earned', 'نقاط جديدة', "اهلا {الاسم}\nزادتلك {النقاط} نقطة على طلب {رقم_الإيصال} ⭐\nرصيد نقاطك: {رصيد_النقاط} نقطة = {قيمة_النقاط} د.ل خصم"],
        ];
        if (Schema::hasTable('whatsapp_messages')) {
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

        // صلاحيات
        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            $names = ['courier-list' => 'عرض المندوبين', 'courier-create' => 'إضافة مندوب', 'courier-edit' => 'تعديل التوصيل والمندوب', 'courier-settle' => 'تسكير حساب المندوب', 'points-edit' => 'تعديل نقاط الزبائن'];
            foreach ($names as $p => $ar) {
                if (!DB::table('permissions')->where('name', $p)->exists()) {
                    $row = ['name' => $p, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()];
                    if ($hasAr) {
                        $row['ar_name'] = $ar;
                    }
                    DB::table('permissions')->insert($row);
                }
            }
            $ids = DB::table('permissions')->whereIn('name', ['courier-list', 'courier-create', 'courier-edit', 'courier-settle', 'points-edit'])->pluck('id');
            $roles = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', 'treasury-list')->pluck('role_has_permissions.role_id')->unique();
            foreach ($roles as $r) {
                foreach ($ids as $pid) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $pid, 'role_id' => $r]);
                }
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /** SQLite: يشيل قيد CHECK متاع enum (للتجربة على sqlite بس) */
    protected static function sqliteDropEnum(string $table, string $column): void
    {
        $sql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        if (!$sql) {
            return;
        }
        $new = preg_replace('/check\s*\(\s*"' . preg_quote($column, '/') . '"\s+in\s*\([^)]*\)\s*\)/iu', '', $sql->sql);
        if ($new !== $sql->sql) {
            DB::statement('PRAGMA writable_schema = ON');
            DB::update("UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = ?", [$new, $table]);
            DB::statement('PRAGMA writable_schema = OFF');
            $v = DB::selectOne('PRAGMA schema_version')->schema_version;
            DB::statement('PRAGMA schema_version = ' . ((int) $v + 1));
        }
    }

    public function down()
    {
        foreach (['customer_messages', 'points_history', 'sg_alerts', 'courier_settlements', 'delivery_events', 'delivery_order', 'deliveries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
