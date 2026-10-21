<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 🌍 عملاء التجارة: رصيد دولار ورصيد دينار (موجب = فلوسه عندنا · سالب = دين عليه) + خزينة دولار — جداول جديدة بس */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('trade_customers')) {
            Schema::create('trade_customers', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('phone', 30)->nullable();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->double('usd')->default(0);
                $t->double('lyd')->default(0);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('trade_cmoves')) {
            Schema::create('trade_cmoves', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('trade_customer_id')->index();
                $t->unsignedBigInteger('invoice_id')->nullable()->index();
                $t->string('type', 20);           // deposit · charge · debt · withdraw · adjust
                $t->string('cur', 5);             // usd · lyd
                $t->double('amount');             // + لرصيده · − من رصيده
                $t->double('lyd_value')->default(0);
                $t->double('rate')->nullable();
                $t->double('after')->nullable();
                $t->text('note')->nullable();
                $t->unsignedBigInteger('created_id')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('trade_invoices') && !Schema::hasColumn('trade_invoices', 'trade_customer_id')) {
            Schema::table('trade_invoices', function (Blueprint $t) {
                $t->unsignedBigInteger('trade_customer_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('trade_wallets') && !DB::table('trade_wallets')->where('kind', 'usd')->exists()) {
            DB::table('trade_wallets')->insert(['name' => '💲 دولار التجارة', 'kind' => 'usd', 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down()
    {
    }
};
