<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** إشعارات الجوال المجانية (Web Push) */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_id')->index();
                $t->text('endpoint');
                $t->char('endpoint_hash', 64)->unique();
                $t->string('p256dh', 200)->nullable();
                $t->string('auth', 100)->nullable();
                $t->string('agent', 200)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
