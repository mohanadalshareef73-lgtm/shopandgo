<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('paid_histories', 'payment_id')) {
            Schema::table('paid_histories', function (Blueprint $table) {
                $table->foreignId('payment_id')
                    ->default(1)
                    ->after('order_id') // since platform_id doesn't exist here
                    ->constrained('payment_methods')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('paid_histories', 'payment_id')) {
            Schema::table('paid_histories', function (Blueprint $table) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            });
        }
    }
};
