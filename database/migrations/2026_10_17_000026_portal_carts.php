<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** العميل يحسب سلته من حسابه ويطلبها */
return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $t) {
            if (!Schema::hasColumn('cart_quotes', 'customer_id')) {
                $t->unsignedBigInteger('customer_id')->nullable()->index();
            }
            if (!Schema::hasColumn('cart_quotes', 'portal_status')) {
                $t->string('portal_status', 20)->nullable()->index(); // draft | ordered | confirmed | rejected
            }
            if (!Schema::hasColumn('cart_quotes', 'ordered_at')) {
                $t->timestamp('ordered_at')->nullable();
            }
            if (!Schema::hasColumn('cart_quotes', 'confirmed_at')) {
                $t->timestamp('confirmed_at')->nullable();
            }
            if (!Schema::hasColumn('cart_quotes', 'receipt_path')) {
                $t->string('receipt_path')->nullable();
            }
        });
        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers', 'cart_ban_until')) {
                $t->timestamp('cart_ban_until')->nullable();
            }
            if (!Schema::hasColumn('customers', 'cart_probation_until')) {
                $t->timestamp('cart_probation_until')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('cart_quotes', function (Blueprint $t) {
            foreach (['customer_id', 'portal_status', 'ordered_at', 'confirmed_at', 'receipt_path'] as $c) {
                if (Schema::hasColumn('cart_quotes', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::table('customers', function (Blueprint $t) {
            foreach (['cart_ban_until', 'cart_probation_until'] as $c) {
                if (Schema::hasColumn('customers', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
