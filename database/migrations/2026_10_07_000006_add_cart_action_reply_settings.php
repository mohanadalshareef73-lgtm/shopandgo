<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('shein_confirm_reply')->nullable();
            $table->text('shein_edit_reply')->nullable();
            $table->text('shein_cancel_reply')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['shein_confirm_reply', 'shein_edit_reply', 'shein_cancel_reply']);
        });
    }
};
