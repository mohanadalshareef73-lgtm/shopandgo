<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** حجز السلة كاش عند المندوب: المندوب يمشي يقبض من العميل مباشرة */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('deliveries', 'cart_id')) {
            Schema::table('deliveries', fn (Blueprint $t) => $t->unsignedBigInteger('cart_id')->nullable()->index());
        }
        Schema::table('cart_quotes', function (Blueprint $t) {
            if (!Schema::hasColumn('cart_quotes', 'paid_amount')) {
                $t->decimal('paid_amount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('cart_quotes', 'paid_at')) {
                $t->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};
