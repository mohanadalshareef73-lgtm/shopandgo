<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('amazon_fixed_fee_usd', 12, 4)->default('5');
            $table->decimal('amazon_flat_surcharge_usd', 12, 4)->default('10');
            $table->decimal('amazon_threshold_usd', 12, 4)->default('100');
            $table->decimal('amazon_percentage', 8, 4)->default('10');
        });
    }

    public function down()
    {
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn([
            'amazon_fixed_fee_usd', 'amazon_flat_surcharge_usd', 'amazon_threshold_usd', 'amazon_percentage',
        ]));
    }
};
