<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** VIP يدوي: المدير يحدده */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('customers', 'is_vip')) {
            Schema::table('customers', fn (Blueprint $t) => $t->boolean('is_vip')->default(false));
        }
    }

    public function down()
    {
        if (Schema::hasColumn('customers', 'is_vip')) {
            Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('is_vip'));
        }
    }
};
