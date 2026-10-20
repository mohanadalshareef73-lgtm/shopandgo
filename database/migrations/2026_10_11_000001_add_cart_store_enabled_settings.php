<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('shein_enabled')->default(true);
            $table->boolean('amazon_enabled')->default(true);
            $table->boolean('iherb_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['shein_enabled', 'amazon_enabled', 'iherb_enabled']);
        });
    }
};
