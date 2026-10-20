<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', fn (Blueprint $table) => $table->json('amazon_pricing')->nullable());
    }

    public function down()
    {
        Schema::table('cart_quotes', fn (Blueprint $table) => $table->dropColumn('amazon_pricing'));
    }
};
