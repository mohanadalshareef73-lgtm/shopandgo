<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ديون خارجية على الشركة: الدائن ممكن يكون مش عميل */
return new class extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE depts MODIFY customer_id BIGINT UNSIGNED NULL');
        }
        Schema::table('depts', function (Blueprint $t) {
            if (!Schema::hasColumn('depts', 'phone')) {
                $t->string('phone', 30)->nullable()->after('org');
            }
            if (!Schema::hasColumn('depts', 'due_date')) {
                $t->date('due_date')->nullable()->after('date');
            }
        });
    }

    public function down()
    {
        Schema::table('depts', function (Blueprint $t) {
            if (Schema::hasColumn('depts', 'phone')) {
                $t->dropColumn('phone');
            }
            if (Schema::hasColumn('depts', 'due_date')) {
                $t->dropColumn('due_date');
            }
        });
    }
};
