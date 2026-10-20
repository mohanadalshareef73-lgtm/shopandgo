<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** سجل زيارات المنظومة: كل يوم × مين × أي صفحة = عدد المرات (خفيف وما يكبرش) */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('site_visits')) {
            Schema::create('site_visits', function (Blueprint $t) {
                $t->id();
                $t->date('date');
                $t->string('actor', 10);              // customer · staff · courier · guest
                $t->unsignedBigInteger('actor_id')->default(0);
                $t->string('page', 80);
                $t->string('device', 10)->default('web'); // mobile · web · app
                $t->unsignedInteger('hits')->default(1);
                $t->timestamp('first_at')->nullable();
                $t->timestamp('last_at')->nullable();
                $t->unique(['date', 'actor', 'actor_id', 'page', 'device'], 'sv_unique');
                $t->index(['date', 'actor']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
