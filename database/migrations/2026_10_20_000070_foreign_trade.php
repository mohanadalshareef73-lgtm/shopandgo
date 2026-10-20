<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 🌍 التجارة الخارجية: خزينة بروحها · شركاء وأرصدة · فواتير شراء بمساهمات · سجل حركة — جداول جديدة بس */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('trade_wallets')) {
            Schema::create('trade_wallets', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('kind', 10)->default('cash'); // cash · bank
                $t->double('balance')->default(0);
                $t->timestamps();
            });
            DB::table('trade_wallets')->insert([
                ['name' => '💵 كاش التجارة', 'kind' => 'cash', 'balance' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '🏦 مصرف التجارة', 'kind' => 'bank', 'balance' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        if (!Schema::hasTable('trade_partners')) {
            Schema::create('trade_partners', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('phone', 30)->nullable();
                $t->boolean('is_company')->default(false);
                $t->double('balance')->default(0);
                $t->text('note')->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
            DB::table('trade_partners')->insert(['name' => 'Shop & Go', 'is_company' => true, 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        if (!Schema::hasTable('trade_invoices')) {
            Schema::create('trade_invoices', function (Blueprint $t) {
                $t->id();
                $t->string('code', 30)->unique();
                $t->string('kind', 40)->nullable();          // نوع الفاتورة
                $t->string('source')->nullable();            // الموقع / المورد
                $t->double('usd_total')->default(0);
                $t->double('rate')->default(0);
                $t->double('cost_lyd')->default(0);          // تكلفة الفاتورة بالدينار
                $t->double('extra_lyd')->default(0);         // مصاريف إضافية
                $t->double('sale_lyd')->default(0);          // سعر البيع للعميل
                $t->unsignedBigInteger('wallet_id')->nullable();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->double('collected')->default(0);         // اللي سدده العميل
                $t->string('status', 15)->default('open');   // open · closed
                $t->double('profit')->default(0);
                $t->timestamp('closed_at')->nullable();
                $t->text('note')->nullable();
                $t->unsignedBigInteger('created_id')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('trade_shares')) {
            Schema::create('trade_shares', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('invoice_id')->index();
                $t->unsignedBigInteger('partner_id')->index();
                $t->double('paid')->default(0);
                $t->double('profit')->default(0);
                $t->string('source', 60)->nullable();        // من رصيده · خزينة الشركة (كاش/مصرف) …
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('trade_moves')) {
            Schema::create('trade_moves', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('wallet_id')->nullable()->index();
                $t->unsignedBigInteger('partner_id')->nullable()->index();
                $t->unsignedBigInteger('invoice_id')->nullable()->index();
                $t->string('type', 30);                      // deposit · withdraw · invest · purchase · customer_pay · profit · loss · company_in · to_company
                $t->double('wallet_change')->default(0);     // + دخل الخزينة · − طلع
                $t->double('partner_change')->default(0);    // + لرصيد الشريك · − من رصيده
                $t->double('wallet_after')->nullable();
                $t->double('partner_after')->nullable();
                $t->text('note')->nullable();
                $t->unsignedBigInteger('created_id')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('permissions') && !DB::table('permissions')->where('name', 'trade-manage')->exists()) {
            $id = DB::table('permissions')->insertGetId(['name' => 'trade-manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()] + (Schema::hasColumn('permissions', 'ar_name') ? ['ar_name' => 'التجارة الخارجية'] : []));
            $admin = DB::table('roles')->where('name', 'Admin')->value('id');
            if ($admin && Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $admin]);
            }
            try {
                app()['cache']->forget('spatie.permission.cache');
            } catch (\Throwable $e) {
            }
        }
    }

    public function down()
    {
    }
};
