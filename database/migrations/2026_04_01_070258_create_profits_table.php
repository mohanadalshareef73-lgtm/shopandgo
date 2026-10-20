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
        Schema::create('profits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade')->comment('الربط مع المنصة');
            $table->double('cost');
            $table->date('date');
            $table->string('currency', 20)->default('dinar');
            $table->string('notes')->nullable();
            $table->foreignId('created_id')->constrained('users')->onDelete('cascade')->comment('الربط مع منشئ الطلب');            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('profits');
    }
};
