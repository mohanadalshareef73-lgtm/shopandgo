<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add only if not exists
            if (!Schema::hasColumn('orders', 'currency')) {
                $table->string('currency', 20)
                      ->default('dinar')
                      ->after('total_price')
                      ->comment('عملة الطلبية');
            }

            if (!Schema::hasColumn('orders', 'currency_sign')) {
                $table->string('currency_sign', 10)
                      ->default('د.ل')
                      ->after('currency')
                      ->comment('رمز العملة');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop only if exists
            if (Schema::hasColumn('orders', 'currency_sign')) {
                $table->dropColumn('currency_sign');
            }

            if (Schema::hasColumn('orders', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
