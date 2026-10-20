<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');

            $table->text('note');

            $table->string('note_hash', 64);

            $table->date('note_date');

            $table->string('image')->nullable();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamps();

            $table->unique(
                ['note_hash', 'order_id', 'note_date'],
                'order_notes_note_hash_order_id_date_unique'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_notes');
    }
};
