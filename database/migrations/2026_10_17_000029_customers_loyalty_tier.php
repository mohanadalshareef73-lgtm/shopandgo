<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** آخر مستوى تبلّغ بيه العميل (باش ما نبعتوش الإشعار مرتين) */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('customers', 'loyalty_tier')) {
            Schema::table('customers', fn (Blueprint $t) => $t->string('loyalty_tier', 10)->nullable());
        }
    }

    public function down()
    {
        if (Schema::hasColumn('customers', 'loyalty_tier')) {
            Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('loyalty_tier'));
        }
    }
};
