<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if 'payment_id' column exists, if not, add it
        if (!Schema::hasColumn('treasury_histories', 'payment_id')) {
            Schema::table('treasury_histories', function (Blueprint $table) {
                $table->foreignId('payment_id')->default(1)->constrained('payment_methods')->cascadeOnDelete();
            });
        }

        // Check if 'currency' column exists, if not, add it
        if (!Schema::hasColumn('treasury_histories', 'currency')) {
            Schema::table('treasury_histories', function (Blueprint $table) {
                $table->string('currency', 20)->default('dinar');
            });
        }

        // Check if 'currency_sign' column exists, if not, add it
        if (!Schema::hasColumn('treasury_histories', 'currency_sign')) {
            Schema::table('treasury_histories', function (Blueprint $table) {
                $table->string('currency_sign', 10)->default('د.ل');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop the columns if they exist
        Schema::table('treasury_histories', function (Blueprint $table) {
            if (Schema::hasColumn('treasury_histories', 'payment_id')) {
                $table->dropColumn('payment_id');
            }
            if (Schema::hasColumn('treasury_histories', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('treasury_histories', 'currency_sign')) {
                $table->dropColumn('currency_sign');
            }
        });
    }
};
