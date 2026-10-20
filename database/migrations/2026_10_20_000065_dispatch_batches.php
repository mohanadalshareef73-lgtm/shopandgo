<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 🛵 تجهيز طلعة: مندوب باسم بدون حساب · طلبية/عربون · طابور خارج بنغازي · رقم الطلعة — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('deliveries')) {
            return;
        }
        Schema::table('deliveries', function (Blueprint $t) {
            if (!Schema::hasColumn('deliveries', 'courier_name')) {
                $t->string('courier_name')->nullable()->comment('مندوب بالاسم بدون حساب');
            }
            if (!Schema::hasColumn('deliveries', 'kind')) {
                $t->string('kind', 10)->default('order')->comment('order طلبية · deposit عربون');
            }
            if (!Schema::hasColumn('deliveries', 'zone')) {
                $t->string('zone', 5)->nullable()->comment('in مندوب · out خارج بنغازي');
            }
            if (!Schema::hasColumn('deliveries', 'batch')) {
                $t->string('batch', 20)->nullable()->index();
            }
        });
    }

    public function down()
    {
    }
};
