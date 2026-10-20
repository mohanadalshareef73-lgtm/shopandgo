<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('shein_product_message')->nullable();
        });
        Schema::table('whatsapp_messagess', function (Blueprint $table) {
            $table->timestamp('shein_product_reply_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messagess', fn (Blueprint $table) => $table->dropColumn('shein_product_reply_sent_at'));
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn('shein_product_message'));
    }
};
