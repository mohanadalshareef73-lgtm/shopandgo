<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** طريقة دفع سلة الموقع: تحويل (عربون) أو كاش عند المندوب */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('cart_quotes', 'pay_method')) {
            Schema::table('cart_quotes', fn (Blueprint $t) => $t->string('pay_method', 20)->nullable());
        }
    }

    public function down()
    {
        if (Schema::hasColumn('cart_quotes', 'pay_method')) {
            Schema::table('cart_quotes', fn (Blueprint $t) => $t->dropColumn('pay_method'));
        }
    }
};
