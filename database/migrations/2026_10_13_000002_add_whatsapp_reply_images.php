<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('reply_images')->nullable();
        });
        Schema::table('cart_whatsapp_button_events', function (Blueprint $table) {
            $table->string('reply_image_path')->nullable();
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('reply_images');
        });
        Schema::table('cart_whatsapp_button_events', function (Blueprint $table) {
            $table->dropColumn('reply_image_path');
        });
    }
};
