<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** الشحن: سعر صرف كاش وسعر صرف مصرف — كل شحنة تحفظ السعرين وقت التسجيل + طريقة الدفع المختارة — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('orders')) {
            return;
        }
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'ship_rate_cash')) {
                $t->decimal('ship_rate_cash', 10, 4)->nullable(); // دينار لكل 1$ (كاش) وقت تسجيل الشحنة
            }
            if (!Schema::hasColumn('orders', 'ship_rate_bank')) {
                $t->decimal('ship_rate_bank', 10, 4)->nullable(); // دينار لكل 1$ (مصرف) وقت تسجيل الشحنة
            }
            if (!Schema::hasColumn('orders', 'ship_pay')) {
                $t->string('ship_pay', 6)->nullable(); // cash | bank: أي سعر هو المعتمد توا على الإيصال
            }
        });
    }

    public function down()
    {
    }
};
