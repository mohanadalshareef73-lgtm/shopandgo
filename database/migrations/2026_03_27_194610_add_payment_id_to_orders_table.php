<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ✅ check if column does NOT exist
        if (!Schema::hasColumn('orders', 'payment_id')) {

            Schema::table('orders', function (Blueprint $table) {

                $table->foreignId('payment_id')
                    ->default(1)
                    ->after('platform_id') // optional position
                    ->constrained('payment_methods')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down()
    {
        // ✅ check before drop
        if (Schema::hasColumn('orders', 'payment_id')) {

            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            });
        }
    }
};
