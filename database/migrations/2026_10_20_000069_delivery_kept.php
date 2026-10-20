<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ✓ حجز متأكد من طلبيات دليفري: يقعد ظاهر (احجز الان · نسخ) لين تخفيه بالصح الخضراء — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('deliveries') && !Schema::hasColumn('deliveries', 'kept')) {
            Schema::table('deliveries', function (Blueprint $t) {
                $t->boolean('kept')->default(false);
            });
        }
    }

    public function down()
    {
    }
};
