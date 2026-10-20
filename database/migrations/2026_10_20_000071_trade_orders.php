<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 🌍 طلبية العميل من التجارة الخارجية: تطلع في حسابه عادي وفلوسها تدخل خزينة التجارة — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'trade_invoice_id')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->unsignedBigInteger('trade_invoice_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('trade_invoices') && !Schema::hasColumn('trade_invoices', 'qty')) {
            Schema::table('trade_invoices', function (Blueprint $t) {
                $t->integer('qty')->default(1);
            });
        }
    }

    public function down()
    {
    }
};
