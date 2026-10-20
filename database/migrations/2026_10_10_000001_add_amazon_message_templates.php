<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->json('amazon_messages')->nullable();
        });
    }

    public function down()
    {
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn('amazon_messages'));
    }
};
