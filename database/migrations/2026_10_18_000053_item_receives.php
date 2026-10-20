<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** استلام منتجات الإيصال على دفعات: كم وصل من كل صنف + سجل كل مرة — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'received_qty')) {
            Schema::table('order_items', function (Blueprint $t) {
                $t->unsignedInteger('received_qty')->default(0);
            });
        }
        if (!Schema::hasTable('order_item_receives')) {
            Schema::create('order_item_receives', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('order_id')->index();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->text('lines')->nullable();
                $t->double('paid')->default(0);
                $t->unsignedBigInteger('payment_id')->nullable();
                $t->text('note')->nullable();
                $t->boolean('sent')->default(false);
                $t->timestamps();
            });
        }
    }

    public function down()
    {
    }
};
