<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 🚚 كود شركة التوصيل (خارج بنغازي) — إضافة بس */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('deliveries') && !Schema::hasColumn('deliveries', 'ext_code')) {
            Schema::table('deliveries', function (Blueprint $t) {
                $t->string('ext_code', 60)->nullable()->index()->comment('كود شركة التوصيل');
            });
        }
    }

    public function down()
    {
    }
};
