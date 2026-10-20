<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** مصروفات الشركاء الشخصية كدين، وتسديدها نقداً أو بالخصم من نصيبهم عند توزيع الفائض */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_transactions') && !Schema::hasColumn('partner_transactions', 'via')) {
            Schema::table('partner_transactions', fn (Blueprint $t) => $t->string('via', 20)->nullable()); // distribution = خُصم من نصيبه عند التوزيع
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        if (Schema::hasColumn('partner_transactions', 'via')) {
            Schema::table('partner_transactions', fn (Blueprint $t) => $t->dropColumn('via'));
        }
        Schema::enableForeignKeyConstraints();
    }
};
