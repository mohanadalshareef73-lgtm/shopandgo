<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الدفعة 2: (1) عملة فاتورة الموقع + ربح الإيصال (2) المرتبات والسلف (3) صلاحية فك الحسابات المقفولة
 * كلها إضافات بس — ما فيش حذف ولا تعديل لبيانات قديمة
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $t) {
            $cols = [
                'site_bill_currency' => fn () => $t->string('site_bill_currency', 10)->nullable(),
                'site_profit' => fn () => $t->decimal('site_profit', 14, 2)->nullable(),
                'site_profit_currency' => fn () => $t->string('site_profit_currency', 10)->nullable(),
                'site_profit_rate' => fn () => $t->decimal('site_profit_rate', 10, 4)->nullable(),
                'site_profit_lyd' => fn () => $t->decimal('site_profit_lyd', 14, 2)->nullable(),
                'site_profit_move' => fn () => $t->text('site_profit_move')->nullable(), // JSON: من وين لوين وقداش (باش التعديل يرجّع بالضبط)
                'site_profit_by' => fn () => $t->unsignedBigInteger('site_profit_by')->nullable(),
                'site_profit_at' => fn () => $t->timestamp('site_profit_at')->nullable(),
            ];
            foreach ($cols as $c => $add) {
                if (!Schema::hasColumn('orders', $c)) {
                    $add();
                }
            }
        });
        Schema::table('purchases', function (Blueprint $t) {
            if (!Schema::hasColumn('purchases', 'staff_id')) {
                $t->unsignedBigInteger('staff_id')->nullable();
            }
            if (!Schema::hasColumn('purchases', 'staff_kind')) {
                $t->string('staff_kind', 12)->nullable(); // salary | advance
            }
            if (!Schema::hasColumn('purchases', 'settled_id')) {
                $t->unsignedBigInteger('settled_id')->nullable(); // السلفة تخصمت في أي مرتب
            }
        });
        // ⚡ احجز: انبعت لأي رقم ومتى (يبان في الصفحة)
        Schema::table('cart_quotes', function (Blueprint $t) {
            if (!Schema::hasColumn('cart_quotes', 'book_sent_to')) {
                $t->string('book_sent_to', 30)->nullable();
            }
            if (!Schema::hasColumn('cart_quotes', 'book_sent_at')) {
                $t->timestamp('book_sent_at')->nullable();
            }
        });
        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            if (!DB::table('permissions')->where('name', 'customer-unlock')->where('guard_name', $guard)->exists()) {
                $row = ['name' => 'customer-unlock', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()];
                if (Schema::hasColumn('permissions', 'ar_name')) {
                    $row['ar_name'] = 'فك الحسابات المقفولة';
                }
                DB::table('permissions')->insert($row);
            }
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
            }
        }
    }

    public function down()
    {
    }
};
