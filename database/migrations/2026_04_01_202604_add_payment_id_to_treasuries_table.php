<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('treasuries', function (Blueprint $table) {
            if (!Schema::hasColumn('treasuries', 'payment_id')) {
                $table->foreignId('payment_id')
                    ->unique()
                    ->default(1)
                    ->constrained('payment_methods')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('treasuries', function (Blueprint $table) {
            if (Schema::hasColumn('treasuries', 'payment_id')) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            }
        });
    }
};
