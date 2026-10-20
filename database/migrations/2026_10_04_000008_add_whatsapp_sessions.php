<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('whatsapp_numbers')->nullable();
            $table->string('whatsapp_main_number', 1)->nullable();
        });
        Schema::table('whatsapp_messagess', function (Blueprint $table) {
            $table->string('session_uuid', 32)->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('whatsapp_messagess', function (Blueprint $table) {
            $table->dropIndex(['session_uuid']);
            $table->dropColumn('session_uuid');
        });
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn([
            'whatsapp_numbers', 'whatsapp_main_number',
        ]));
    }
};
