<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 🌍 التجارة: اسم العميل (بدون حساب) · نسبة ربح لكل شريك (حتى لو ما دفعش) — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('trade_invoices') && !Schema::hasColumn('trade_invoices', 'customer_name')) {
            Schema::table('trade_invoices', function (Blueprint $t) {
                $t->string('customer_name')->nullable();
            });
        }
        if (Schema::hasTable('trade_shares') && !Schema::hasColumn('trade_shares', 'pct')) {
            Schema::table('trade_shares', function (Blueprint $t) {
                $t->double('pct')->nullable()->comment('نسبة الربح % (فاضي = على قد اللي دفعه)');
            });
        }
    }

    public function down()
    {
    }
};
