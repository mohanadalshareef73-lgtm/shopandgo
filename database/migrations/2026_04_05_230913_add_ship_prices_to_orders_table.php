<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {

            if (!Schema::hasColumn('orders', 'ship_price_dollar')) {
                $table->double('ship_price_dollar')->nullable()->after('ship_price');
            }

            if (!Schema::hasColumn('orders', 'ship_price_euro')) {
                $table->double('ship_price_euro')->nullable()->after('ship_price_dollar');
            }

            if (!Schema::hasColumn('orders', 'ship_price_bank')) {
                $table->double('ship_price_bank')->nullable()->after('ship_price_euro');
            }

        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {

            if (Schema::hasColumn('orders', 'ship_price_dollar')) {
                $table->dropColumn('ship_price_dollar');
            }

            if (Schema::hasColumn('orders', 'ship_price_euro')) {
                $table->dropColumn('ship_price_euro');
            }

            if (Schema::hasColumn('orders', 'ship_price_bank')) {
                $table->dropColumn('ship_price_bank');
            }

        });
    }
};
