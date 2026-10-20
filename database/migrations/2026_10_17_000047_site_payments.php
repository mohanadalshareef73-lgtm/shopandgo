<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** إيصال دفع مواقع: العميل يدفع لنا، ونحن ندفع للموقع؛ التكلفة تنكتب بعدين والربح ينحسب */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            foreach ([
                'site_name' => fn () => $t->string('site_name', 80)->nullable(),
                'site_cost' => fn () => $t->decimal('site_cost', 14, 2)->nullable(),
                'site_cost_currency' => fn () => $t->string('site_cost_currency', 10)->nullable(),
                'site_cost_rate' => fn () => $t->decimal('site_cost_rate', 10, 4)->nullable(),
                'site_cost_lyd' => fn () => $t->decimal('site_cost_lyd', 14, 2)->nullable(),
                'site_cost_by' => fn () => $t->unsignedBigInteger('site_cost_by')->nullable(),
                'site_cost_at' => fn () => $t->timestamp('site_cost_at')->nullable(),
            ] as $col => $add) {
                if (!Schema::hasColumn('orders', $col)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
    }
};
