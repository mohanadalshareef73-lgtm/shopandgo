<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->string('origin')->default('whatsapp');
        });
    }

    public function down()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
