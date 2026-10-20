<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->string('customer_payment_method', 10)->nullable();
            $table->timestamp('customer_payment_updated_at')->nullable();
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->text('cart_cash_reply')->nullable();
            $table->text('cart_bank_reply')->nullable();
        });
    }

    public function down()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->dropColumn(['customer_payment_method', 'customer_payment_updated_at']);
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['cart_cash_reply', 'cart_bank_reply']);
        });
    }
};
