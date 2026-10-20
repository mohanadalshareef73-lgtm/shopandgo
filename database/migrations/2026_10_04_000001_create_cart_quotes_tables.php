<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cart_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_messages_id')->constrained('whatsapp_messagess')->cascadeOnDelete();
            $table->text('source_url');
            $table->string('idempotency_key', 80)->unique();
            $table->string('quote_id')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_quote_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->unique();
            $table->string('body_hash', 64);
            // No FK lock on the quote: callback inserts must not wait for a
            // worker holding that quote's row during an external API request.
            $table->unsignedBigInteger('cart_quote_id')->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cart_quote_webhooks');
        Schema::dropIfExists('cart_quotes');
    }
};
