<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 🏙 العميل: خانة المدينة والمنطقة (المدينة مش بنغازي → التوصيل ينزل في «خارج بنغازي») */
return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers', 'city')) {
                $t->string('city', 80)->nullable()->after('address');
            }
            if (!Schema::hasColumn('customers', 'area')) {
                $t->string('area', 120)->nullable()->after('city');
            }
        });
    }

    public function down()
    {
    }
};
