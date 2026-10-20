<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** إشعارات الجوال للموظفين والمندوبين + تقييم المندوب */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('push_subscriptions')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE push_subscriptions MODIFY customer_id BIGINT UNSIGNED NULL');
            }
            if (!Schema::hasColumn('push_subscriptions', 'user_id')) {
                Schema::table('push_subscriptions', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->index());
            }
        }
        Schema::table('deliveries', function (Blueprint $t) {
            if (!Schema::hasColumn('deliveries', 'rating')) {
                $t->unsignedTinyInteger('rating')->nullable();
            }
            if (!Schema::hasColumn('deliveries', 'rating_note')) {
                $t->string('rating_note', 500)->nullable();
            }
            if (!Schema::hasColumn('deliveries', 'rated_at')) {
                $t->timestamp('rated_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('deliveries', function (Blueprint $t) {
            foreach (['rating', 'rating_note', 'rated_at'] as $c) {
                if (Schema::hasColumn('deliveries', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
