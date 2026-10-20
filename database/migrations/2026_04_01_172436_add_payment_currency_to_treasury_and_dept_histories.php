<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // treasury_histories
        if (!Schema::hasColumn('treasury_histories', 'payment_id')) {
            Schema::table('treasury_histories', function (Blueprint $table) {
                $table->foreignId('payment_id')
                    ->default(1)
                    ->constrained('payment_methods')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('treasury_histories', 'currency')) {
            Schema::table('treasury_histories', function (Blueprint $table) {
                $table->string('currency', 20)->default('dinar');
            });
        }

        if (!Schema::hasColumn('treasury_histories', 'currency_sign')) {
            Schema::table('treasury_histories', function (Blueprint $table) {
                $table->string('currency_sign', 10)->default('د.ل');
            });
        }

        // dept_histories
        if (!Schema::hasColumn('dept_histories', 'payment_id')) {
            Schema::table('dept_histories', function (Blueprint $table) {
                $table->foreignId('payment_id')
                    ->default(1)
                    ->constrained('payment_methods')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('dept_histories', 'currency')) {
            Schema::table('dept_histories', function (Blueprint $table) {
                $table->string('currency', 20)->default('dinar');
            });
        }

        if (!Schema::hasColumn('dept_histories', 'currency_sign')) {
            Schema::table('dept_histories', function (Blueprint $table) {
                $table->string('currency_sign', 10)->default('د.ل');
            });
        }
    }

    public function down()
    {
        // treasury_histories
        Schema::table('treasury_histories', function (Blueprint $table) {
            if (Schema::hasColumn('treasury_histories', 'payment_id')) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            }

            if (Schema::hasColumn('treasury_histories', 'currency')) {
                $table->dropColumn('currency');
            }

            if (Schema::hasColumn('treasury_histories', 'currency_sign')) {
                $table->dropColumn('currency_sign');
            }
        });

        // dept_histories
        Schema::table('dept_histories', function (Blueprint $table) {
            if (Schema::hasColumn('dept_histories', 'payment_id')) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            }

            if (Schema::hasColumn('dept_histories', 'currency')) {
                $table->dropColumn('currency');
            }

            if (Schema::hasColumn('dept_histories', 'currency_sign')) {
                $table->dropColumn('currency_sign');
            }
        });
    }
};
