<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whatsapp_messagess', function (Blueprint $table) {
            $table->id();
            // i want to add them here
            $table->string('from');
            $table->string('phone');
            $table->string('name');
            $table->string('type');
            $table->text('message');
            $table->timestamp('timestamp');
            $table->string('message_id');
            $table->boolean('is_reply')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
