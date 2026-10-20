<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_status_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_quote_id')->constrained('cart_quotes')->cascadeOnDelete();
            $table->string('previous_status');
            $table->string('new_status');
            $table->string('previous_customer_status');
            $table->string('new_customer_status');
            $table->text('previous_notes')->nullable();
            $table->text('new_notes')->nullable();
            $table->string('actor_type', 20);
            $table->string('actor_name');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_phone', 15)->nullable();
            $table->string('session_uuid', 32)->nullable();
            $table->string('event_id')->nullable()->unique();
            $table->timestamp('changed_at');
            $table->index(['cart_quote_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_status_audits');
    }
};
