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
        Schema::create('depts', function (Blueprint $table) {
            $table->id();
            $table->string('org')->nullable();
            $table->double('total');
            $table->enum('status', ['paid', 'dept'])->comment('حالة الدين');
            $table->date('date');
            $table->text('note')->nullable();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade')->comment('الربط مع الزبون');
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
        Schema::dropIfExists('depts');
    }
};
