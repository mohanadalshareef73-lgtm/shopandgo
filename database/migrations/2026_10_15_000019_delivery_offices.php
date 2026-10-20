<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** مكاتب التوصيل الخارجية: تُسجَّل كمندوب نوعه «مكتب» (يقبض من العميل أحياناً، وأجرته علينا) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'courier_kind')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('courier_kind', 10)->nullable()); // person · office
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'courier_kind')) {
            Schema::disableForeignKeyConstraints();
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('courier_kind'));
            Schema::enableForeignKeyConstraints();
        }
    }
};
