<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بطاقات SHEIN (بطاقات هدايا / رصيد):
 *  - نشريو البطاقة بسعر (دينار أو دولار) ونعرفو قيمتها بالدولار في SHEIN
 *  - نستعملوها في رحلة شراء (أو إيصال) → تنقص من رصيدها وتدخل في تكلفة الرحلة بسعرها الحقيقي
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('shein_cards')) {
            Schema::create('shein_cards', function (Blueprint $table) {
                $table->id();
                $table->string('card_no', 120)->unique()->comment('رقم/كود البطاقة');
                $table->text('pin')->nullable()->comment('الرقم السري (مشفر)');
                $table->double('face_value')->comment('قيمة البطاقة بالدولار');
                $table->double('balance')->comment('الرصيد الباقي بالدولار');
                $table->double('cost')->default(0)->comment('اللي دفعناه في البطاقة');
                $table->string('cost_currency', 10)->default('dinar');
                $table->double('cost_dinar')->default(0)->comment('تكلفة البطاقة بالدينار (للربح)');
                $table->foreignId('payment_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->string('supplier')->nullable()->comment('من وين شريناها');
                $table->string('batch', 40)->nullable()->comment('رقم الدفعة لما نشريو مجموعة');
                $table->string('status', 20)->default('new')->comment('new | partial | used | expired | invalid');
                $table->string('account')->nullable()->comment('حساب SHEIN اللي انشحنت فيه');
                $table->timestamp('redeemed_at')->nullable();
                $table->date('purchased_at');
                $table->date('expires_at')->nullable();
                $table->double('lost_dinar')->default(0)->comment('خسارة لو انتهت أو طلعت غلط');
                $table->timestamp('lost_at')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('status');
            });
        }
        if (!Schema::hasTable('shein_card_uses')) {
            Schema::create('shein_card_uses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('card_id')->constrained('shein_cards')->cascadeOnDelete();
                $table->foreignId('trip_purchase_id')->nullable()->constrained('trip_purchases')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->double('amount')->comment('بالدولار');
                $table->double('cost_dinar')->default(0)->comment('تكلفة الجزء المستعمل بالدينار');
                $table->text('note')->nullable();
                $table->foreignId('created_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        Schema::table('trip_purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_purchases', 'cards_amount')) {
                $table->double('cards_amount')->default(0)->comment('جزء الرحلة المدفوع ببطاقات SHEIN ($)');
            }
            if (!Schema::hasColumn('trip_purchases', 'cards_cost_dinar')) {
                $table->double('cards_cost_dinar')->default(0)->comment('تكلفة البطاقات المستعملة بالدينار');
            }
        });

        if (Schema::hasTable('permissions')) {
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            $names = [
                'shein-card-list' => 'عرض بطاقات SHEIN', 'shein-card-create' => 'شراء بطاقات SHEIN', 'shein-card-use' => 'استعمال بطاقات SHEIN',
                'shein-card-delete' => 'حذف بطاقات SHEIN', 'shein-card-pin' => 'عرض الرقم السري للبطاقات',
            ];
            foreach ($names as $p => $ar) {
                if (!DB::table('permissions')->where('name', $p)->exists()) {
                    $row = ['name' => $p, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                    if ($hasAr) {
                        $row['ar_name'] = $ar;
                    }
                    DB::table('permissions')->insert($row);
                }
            }
            // الأدوار اللي عندها شراء الرحلات تاخذ صلاحيات البطاقات
            $ids = DB::table('permissions')->whereIn('name', array_keys($names))->pluck('id');
            $roles = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->whereIn('permissions.name', ['trip-purchase-create', 'treasury-list'])->pluck('role_has_permissions.role_id')->unique();
            foreach ($roles as $r) {
                foreach ($ids as $pid) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $pid, 'role_id' => $r]);
                }
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('shein_card_uses');
        Schema::dropIfExists('shein_cards');
        $cols = array_values(array_filter(['cards_amount', 'cards_cost_dinar'], fn ($c) => Schema::hasColumn('trip_purchases', $c)));
        if ($cols) {
            Schema::table('trip_purchases', fn (Blueprint $t) => $t->dropColumn($cols));
        }
        Schema::enableForeignKeyConstraints();
    }
};
