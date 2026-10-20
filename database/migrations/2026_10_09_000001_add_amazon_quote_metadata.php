<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->string('retailer')->default('shein');
            $table->text('provider_idempotency_key')->nullable();
            $table->unsignedInteger('requested_quantity')->default(1);
            $table->unsignedInteger('capacity_retries')->default(0);
            $table->timestamp('deadline_checked_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('cart_quotes', fn (Blueprint $table) => $table->dropColumn([
            'retailer', 'provider_idempotency_key', 'requested_quantity', 'capacity_retries', 'deadline_checked_at',
        ]));
    }
};
