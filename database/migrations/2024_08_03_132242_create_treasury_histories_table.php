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
        Schema::create('treasury_histories', function (Blueprint $table) {
            $table->id();
            $table->double('price');
            $table->double('price_before');
            $table->double('price_after');
            $table->boolean('is_update')->default(false);
            $table->enum('status', ['pay', 'pull','update'])->comment('حالة الاجراء');
            $table->enum('type', ['order', 'trip','customer','shippingCharge','purchase','paymentExpenses','user','dept'])->comment('نوع الاجراء بالتفصيل');
            $table->foreignId('procedure_id')->comment('الربط مع الاجراء من اي جدول');
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
        Schema::dropIfExists('treasury_histories');
    }
};
