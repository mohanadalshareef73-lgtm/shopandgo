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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade')->comment('الربط مع الزبون');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->onDelete('cascade')->comment('الربط مع الرحلة');
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade')->comment('الربط مع المنصة');
            $table->string('code')->unique();
            $table->enum('status', ['paid_full', 'paid', 'unpaid','canceld','delivered'])->comment('حالة الطلبية');
            $table->integer('qty');
            $table->integer('bags_qty')->nullable();
            $table->integer('bags_num')->nullable();
            $table->double('cost_price')->nullable();
            $table->double('total_price');
            $table->double('paid_price')->default(0);
            $table->double('ship_price')->nullable();
            $table->double('ship_price_dollar')->nullable();
            $table->double('ship_price_euro')->nullable();
            $table->double('ship_price_bank')->nullable();

                        $table->double('discount')->nullable();
            $table->text('discount_reason')->nullable();
            $table->text('discount_reason_note')->nullable();

            $table->float('kilo')->nullable();
            $table->string('note')->nullable();
            $table->string('trip_details')->nullable();
            $table->boolean('delivered')->default(false);
            $table->date('delivered_at')->nullable();
            $table->boolean('arrived')->default(false);
            $table->date('arrived_at')->nullable();
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
        Schema::dropIfExists('orders');
    }
};
