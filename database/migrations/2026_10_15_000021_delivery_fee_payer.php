<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** من يدفع أجرة التوصيل: العميل (افتراضي) أو الشركة */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('deliveries', 'fee_payer')) {
            Schema::table('deliveries', function (Blueprint $t) {
                $t->string('fee_payer', 10)->default('customer')->after('fee');
            });
        }
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        if (Schema::hasColumn('deliveries', 'fee_payer')) {
            Schema::table('deliveries', function (Blueprint $t) {
                $t->dropColumn('fee_payer');
            });
        }
        Schema::enableForeignKeyConstraints();
    }
};
