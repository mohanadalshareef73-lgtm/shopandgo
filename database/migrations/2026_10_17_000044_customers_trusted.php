<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** عميل موثوق ✅: يأكد طلب السلة بدون عربون مهما كانت القيمة */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'trusted')) {
            Schema::table('customers', fn (Blueprint $t) => $t->boolean('trusted')->default(false));
        }
    }

    public function down(): void
    {
    }
};
