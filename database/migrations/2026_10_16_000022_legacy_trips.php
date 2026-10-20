<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** رحلات المنظومة القديمة: تُستورد كرحلات عادية (legacy) بدون أي حركة في الخزينة */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('trip_purchases', 'legacy')) {
            Schema::table('trip_purchases', fn (Blueprint $t) => $t->boolean('legacy')->default(false));
        }
    }

    public function down()
    {
        if (Schema::hasColumn('trip_purchases', 'legacy')) {
            Schema::disableForeignKeyConstraints();
            Schema::table('trip_purchases', fn (Blueprint $t) => $t->dropColumn('legacy'));
            Schema::enableForeignKeyConstraints();
        }
    }
};
