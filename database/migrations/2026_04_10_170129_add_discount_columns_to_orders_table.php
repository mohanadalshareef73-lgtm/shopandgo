<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountColumnsToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if the 'discount' column exists before adding it
        if (!Schema::hasColumn('orders', 'discount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->double('discount')->nullable(); // Add the 'discount' column
            });
        }

        // Check if the 'discount_reason' column exists before adding it
        if (!Schema::hasColumn('orders', 'discount_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('discount_reason')->nullable(); // Add the 'discount_reason' column
            });
        }

        // Check if the 'discount_reason_note' column exists before adding it
        if (!Schema::hasColumn('orders', 'discount_reason_note')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('discount_reason_note')->nullable(); // Add the 'discount_reason_note' column
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
        Schema::table('orders', function (Blueprint $table) {
            // Drop the columns if they were added
            $table->dropColumn(['discount', 'discount_reason', 'discount_reason_note']);
        });
    }
}
