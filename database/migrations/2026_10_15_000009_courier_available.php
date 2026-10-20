<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** المندوب: متاح للتوصيل اليوم أم لا */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'courier_available')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('courier_available')->default(true));
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        if (Schema::hasColumn('users', 'courier_available')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('courier_available'));
        }
        Schema::enableForeignKeyConstraints();
    }
};
