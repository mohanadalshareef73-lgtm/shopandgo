<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 🌍 أسماء المنتجات في فاتورة التجارة — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('trade_invoices') && !Schema::hasColumn('trade_invoices', 'items')) {
            Schema::table('trade_invoices', function (Blueprint $t) {
                $t->text('items')->nullable()->comment('JSON: [{name, qty, price}]');
            });
        }
    }

    public function down()
    {
    }
};
