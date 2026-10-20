<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** عملة دفع الجزء النقدي من الرحلة (دولار من خزينة الدولار، أو دينار بسعر صرف الرحلة) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('trip_purchases', 'pay_currency')) {
            Schema::table('trip_purchases', fn (Blueprint $t) => $t->string('pay_currency', 10)->nullable());
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        if (Schema::hasColumn('trip_purchases', 'pay_currency')) {
            Schema::table('trip_purchases', fn (Blueprint $t) => $t->dropColumn('pay_currency'));
        }
        Schema::enableForeignKeyConstraints();
    }
};
