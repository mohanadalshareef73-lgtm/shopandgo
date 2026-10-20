<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ادعُ صديق + تقييم الطلبيات */
return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers', 'referred_by')) {
                $t->unsignedBigInteger('referred_by')->nullable()->index();
            }
            if (!Schema::hasColumn('customers', 'referral_rewarded')) {
                $t->boolean('referral_rewarded')->default(false);
            }
        });
        Schema::table('orders', function (Blueprint $t) {
            if (!Schema::hasColumn('orders', 'rating')) {
                $t->unsignedTinyInteger('rating')->nullable();
            }
            if (!Schema::hasColumn('orders', 'rating_note')) {
                $t->string('rating_note', 500)->nullable();
            }
            if (!Schema::hasColumn('orders', 'rated_at')) {
                $t->timestamp('rated_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $t) {
            foreach (['referred_by', 'referral_rewarded'] as $c) {
                if (Schema::hasColumn('customers', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::table('orders', function (Blueprint $t) {
            foreach (['rating', 'rating_note', 'rated_at'] as $c) {
                if (Schema::hasColumn('orders', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
