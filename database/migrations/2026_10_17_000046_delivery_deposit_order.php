<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** المندوب يقبض عربون إيصال موجود (من غير ما يسلّم الطلب) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('deliveries', 'deposit_order_id')) {
            Schema::table('deliveries', fn (Blueprint $t) => $t->unsignedBigInteger('deposit_order_id')->nullable());
        }
    }

    public function down(): void
    {
    }
};
