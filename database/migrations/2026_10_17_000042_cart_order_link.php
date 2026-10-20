<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** السلة اللي ندفع عربونها للمندوب: كم اختار يدفع، والإيصال اللي تعمل منها */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_quotes', function (Blueprint $t) {
            if (!Schema::hasColumn('cart_quotes', 'courier_pay')) {
                $t->decimal('courier_pay', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('cart_quotes', 'order_id')) {
                $t->unsignedBigInteger('order_id')->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};
