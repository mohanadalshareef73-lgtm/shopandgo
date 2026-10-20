<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** الدفعات: مين قبضها وشن نوعها (دفعة / تسديد دين / تسليم …) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paid_histories', function (Blueprint $t) {
            if (!Schema::hasColumn('paid_histories', 'created_id')) {
                $t->unsignedBigInteger('created_id')->nullable()->index();
            }
            if (!Schema::hasColumn('paid_histories', 'note')) {
                $t->string('note', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        $cols = array_values(array_filter(['created_id', 'note'], fn ($c) => Schema::hasColumn('paid_histories', $c)));
        if ($cols) {
            Schema::table('paid_histories', fn (Blueprint $t) => $t->dropColumn($cols));
        }
        Schema::enableForeignKeyConstraints();
    }
};
