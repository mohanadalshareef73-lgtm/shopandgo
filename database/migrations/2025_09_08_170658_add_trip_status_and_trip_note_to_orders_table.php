<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'trip_status')) {
                $table->string('trip_status')->nullable()->default('قيد التجهيز')->after('trip_details');
            }

            if (!Schema::hasColumn('orders', 'trip_note')) {
                $table->text('trip_note')->nullable()->after('trip_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'trip_status')) {
                $table->dropColumn('trip_status');
            }

            if (Schema::hasColumn('orders', 'trip_note')) {
                $table->dropColumn('trip_note');
            }
        });
    }
};
