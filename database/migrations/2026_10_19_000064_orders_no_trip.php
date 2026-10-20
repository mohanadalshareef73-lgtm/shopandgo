<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «بدون رحلة»: طلبية ما تدخل رحلة (مثل شي إن / علي بابا) — الزبون يشوف «سيصل قريباً» بدل حالة الرحلة — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'no_trip')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->boolean('no_trip')->default(false);
            });
        }
    }

    public function down()
    {
    }
};
