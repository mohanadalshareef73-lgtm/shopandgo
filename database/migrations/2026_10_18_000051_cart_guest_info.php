<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** بيانات العميل اللي يحجز من رابط السلة بدون حساب (اسم + رقم + عنوان) — إضافة أعمدة بس */
return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $t) {
            if (!Schema::hasColumn('cart_quotes', 'guest_name')) {
                $t->string('guest_name', 120)->nullable();
            }
            if (!Schema::hasColumn('cart_quotes', 'guest_phone')) {
                $t->string('guest_phone', 30)->nullable()->index();
            }
            if (!Schema::hasColumn('cart_quotes', 'guest_address')) {
                $t->string('guest_address', 200)->nullable();
            }
        });
    }

    public function down()
    {
    }
};
