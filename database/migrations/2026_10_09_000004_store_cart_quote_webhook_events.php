<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Keep the existing unique Webhook-Id constraint and durable inbox.
        Schema::table('cart_quote_webhooks', function (Blueprint $table) {
            $table->json('payload')->nullable();
        });
        Schema::table('cart_quotes', fn (Blueprint $table) => $table->timestamp('submitted_at')->nullable());
    }

    public function down()
    {
        Schema::table('cart_quotes', fn (Blueprint $table) => $table->dropColumn('submitted_at'));
        Schema::table('cart_quote_webhooks', fn (Blueprint $table) => $table->dropColumn('payload'));
    }
};
