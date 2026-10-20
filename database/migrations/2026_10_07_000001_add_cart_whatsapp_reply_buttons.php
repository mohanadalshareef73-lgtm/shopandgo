<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->string('whatsapp_cart_reference')->nullable()->unique();
            $table->string('whatsapp_customer_phone', 15)->nullable();
            $table->string('whatsapp_session_uuid', 32)->nullable();
            $table->timestamp('whatsapp_queued_at')->nullable();
            $table->string('customer_status')->default('open');
            $table->timestamp('customer_updated_at')->nullable();
        });

        // No cart FK: webhook inserts must not wait for quotation delivery locks.
        Schema::create('cart_whatsapp_button_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('action', 10);
            $table->string('cart_reference');
            $table->string('customer_phone', 15);
            $table->string('session_uuid', 32);
            $table->text('context_message_id')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamp('state_applied_at')->nullable();
            $table->text('reply_text')->nullable();
            $table->timestamp('reply_queued_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cart_whatsapp_button_events');
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_cart_reference']);
            $table->dropColumn(['whatsapp_cart_reference', 'whatsapp_customer_phone', 'whatsapp_session_uuid',
                'whatsapp_queued_at', 'customer_status', 'customer_updated_at']);
        });
    }
};
