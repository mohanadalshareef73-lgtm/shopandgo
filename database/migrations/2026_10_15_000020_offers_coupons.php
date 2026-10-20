<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** العروض والخصومات والمناسبات + الكوبونات (من العروض أو من تحويل النقاط) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('offers')) {
            Schema::create('offers', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 20)->default('offer');      // offer · discount · occasion
                $t->string('title', 150);
                $t->text('body')->nullable();
                $t->string('discount_type', 10)->nullable();    // percent · amount
                $t->decimal('discount_value', 10, 2)->nullable();
                $t->decimal('max_discount', 10, 2)->nullable();
                $t->decimal('min_order', 10, 2)->nullable();
                $t->string('coupon_code', 30)->nullable()->index();
                $t->unsignedInteger('uses_per_customer')->nullable();
                $t->date('starts_at')->nullable();
                $t->date('ends_at')->nullable();
                $t->boolean('active')->default(true);
                $t->unsignedInteger('notified')->default(0);
                $t->unsignedBigInteger('created_id')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $t) {
                $t->id();
                $t->string('code', 30)->unique();
                $t->unsignedBigInteger('customer_id')->nullable()->index(); // null = عام (من عرض)
                $t->unsignedBigInteger('offer_id')->nullable()->index();
                $t->string('source', 20)->default('manual');               // points · offer · manual
                $t->string('type', 10)->default('percent');                 // percent · amount
                $t->decimal('value', 10, 2);
                $t->decimal('max_discount', 10, 2)->nullable();
                $t->decimal('min_order', 10, 2)->nullable();
                $t->unsignedInteger('uses_limit')->nullable();              // null = بلا حد
                $t->unsignedInteger('uses_per_customer')->nullable();
                $t->unsignedInteger('used_count')->default(0);
                $t->unsignedInteger('points_spent')->default(0);
                $t->date('expires_at')->nullable();
                $t->boolean('active')->default(true);
                $t->unsignedBigInteger('created_id')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('coupon_uses')) {
            Schema::create('coupon_uses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('coupon_id')->index();
                $t->unsignedBigInteger('order_id')->index();
                $t->unsignedBigInteger('customer_id')->nullable();
                $t->decimal('amount', 10, 2);
                $t->unsignedBigInteger('created_id')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('permissions') && !DB::table('permissions')->where('name', 'offer-manage')->exists()) {
            $row = ['name' => 'offer-manage', 'guard_name' => config('auth.defaults.guard', 'web'), 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('permissions', 'ar_name')) {
                $row['ar_name'] = 'العروض والكوبونات';
            }
            $id = DB::table('permissions')->insertGetId($row);
            $admin = DB::table('roles')->where('name', 'Admin')->value('id');
            if ($admin && Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $admin]);
            }
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_uses');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('offers');
    }
};
