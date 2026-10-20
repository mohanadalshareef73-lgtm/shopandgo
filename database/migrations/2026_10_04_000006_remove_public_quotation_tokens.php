<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->dropUnique(['pdf_access_token']);
            $table->dropColumn(['pdf_access_token', 'pdf_expires_at']);
        });
    }

    public function down()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->string('pdf_access_token', 64)->nullable()->unique();
            $table->timestamp('pdf_expires_at')->nullable();
        });
    }
};
