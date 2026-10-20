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
        Schema::create('payment_expenses', function (Blueprint $table) {
            $table->id();
            $table->integer('from');
            $table->integer('to');
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade')->comment('الربط مع نوع المنصة');
            $table->double('cost');
            $table->date('date');
            $table->text('note')->nullable();
            $table->boolean('status')->default(false);
            $table->foreignId('created_id')->constrained('users')->onDelete('cascade')->comment('الربط مع منشئ الطلب');
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
        Schema::dropIfExists('payment_expenses');
    }
};
