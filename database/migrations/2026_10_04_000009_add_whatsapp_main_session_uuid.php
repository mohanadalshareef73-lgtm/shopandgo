<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('whatsapp_main_session_uuid', 32)->nullable();
        });

        // Preserve the chosen sender when moving away from fixed number slots.
        DB::table('settings')->orderBy('id')->each(function ($settings) {
            $numbers = json_decode($settings->whatsapp_numbers ?? '[]', true);
            $uuid = $numbers[$settings->whatsapp_main_number ?? '']['session_uuid'] ?? null;
            if (is_string($uuid) && preg_match('/^[a-fA-F0-9]{32}$/D', $uuid) === 1) {
                DB::table('settings')->where('id', $settings->id)->update(['whatsapp_main_session_uuid' => $uuid]);
            }
        });
    }

    public function down()
    {
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn('whatsapp_main_session_uuid'));
    }
};
