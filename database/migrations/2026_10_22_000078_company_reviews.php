<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ⭐ تقييمات الشركة: الزبون يقيّم بعد التسليم (كلام وصورة)، والإدارة توافق قبل ما يطلع */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('company_reviews')) {
            Schema::create('company_reviews', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->unsignedTinyInteger('rating')->default(5);
                $t->text('body')->nullable();
                $t->string('photo')->nullable();
                $t->string('status', 20)->default('pending')->index(); // pending / approved / rejected
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down()
    {
    }
};
