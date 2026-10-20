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
        // Add payment_id column and check if it already exists in 'purchases'
        if (!Schema::hasColumn('purchases', 'payment_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (Schema::hasColumn('purchases', 'platform_id')) {
                    // If platform_id exists, add payment_id after it
                    $table->foreignId('payment_id')
                        ->default(1)
                        ->after('platform_id')
                        ->constrained('payment_methods')
                        ->cascadeOnDelete();
                } else {
                    // Otherwise, just add payment_id at the end
                    $table->foreignId('payment_id')
                        ->default(1)
                        ->constrained('payment_methods')
                        ->cascadeOnDelete();
                }

            });
        }

        // Add currency column to 'purchases' if not exists
        if (!Schema::hasColumn('purchases', 'currency')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->string('currency', 20)->default('dinar');
            });
        }
        if (!Schema::hasColumn('purchases', 'currency_sign')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->string('currency_sign', 10)
                ->default('د.ل')
                      ->after('currency')
                      ->comment('رمز العملة');
            });
        }


        // Add payment_id column and check if it already exists in 'shipping_charges'
        if (!Schema::hasColumn('shipping_charges', 'payment_id')) {
            Schema::table('shipping_charges', function (Blueprint $table) {
                if (Schema::hasColumn('shipping_charges', 'platform_id')) {
                    // If platform_id exists, add payment_id after it
                    $table->foreignId('payment_id')
                        ->default(1)
                        ->after('platform_id')
                        ->constrained('payment_methods')
                        ->cascadeOnDelete();
                } else {
                    // Otherwise, just add payment_id at the end
                    $table->foreignId('payment_id')
                        ->default(1)
                        ->constrained('payment_methods')
                        ->cascadeOnDelete();
                }
            });
        }

        // Add currency column to 'shipping_charges' if not exists
        if (!Schema::hasColumn('shipping_charges', 'currency')) {
            Schema::table('shipping_charges', function (Blueprint $table) {
                $table->string('currency', 20)->default('dinar');
            });
        }
        if (!Schema::hasColumn('shipping_charges', 'currency_sign')) {
            Schema::table('shipping_charges', function (Blueprint $table) {
                $table->string('currency_sign', 10)
                ->default('د.ل')
                      ->after('currency')
                      ->comment('رمز العملة');
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
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'payment_id')) {
                $table->dropColumn('payment_id');
            }
            if (Schema::hasColumn('purchases', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('purchases', 'currency_sign')) {
                $table->dropColumn('currency_sign');
            }
        });

        Schema::table('shipping_charges', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_charges', 'payment_id')) {
                $table->dropColumn('payment_id');
            }
            if (Schema::hasColumn('shipping_charges', 'currency')) {
                $table->dropColumn('currency');
            }
                        if (Schema::hasColumn('shipping_charges', 'currency_sign')) {
                $table->dropColumn('currency_sign');
            }
        });
    }
};
