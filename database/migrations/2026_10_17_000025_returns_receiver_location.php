<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** المرتجع: مين استلمه من الزبون، ووين حطيناه */
return new class extends Migration
{
    public function up()
    {
        Schema::table('order_returns', function (Blueprint $t) {
            if (!Schema::hasColumn('order_returns', 'received_by')) {
                $t->unsignedBigInteger('received_by')->nullable();
            }
            if (!Schema::hasColumn('order_returns', 'location')) {
                $t->string('location', 120)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('order_returns', function (Blueprint $t) {
            foreach (['received_by', 'location'] as $c) {
                if (Schema::hasColumn('order_returns', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
