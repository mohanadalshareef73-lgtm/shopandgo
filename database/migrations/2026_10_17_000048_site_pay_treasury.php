<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** دفع مواقع: قيمة الفاتورة بالدولار + من أي خزينة انسحبت الكلفة */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'site_bill_usd')) {
                $t->decimal('site_bill_usd', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('orders', 'site_cost_pid')) {
                $t->unsignedBigInteger('site_cost_pid')->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};
