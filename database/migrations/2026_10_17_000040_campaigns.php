<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** حملات واتساب على دفعات (نشطين ← متوسطين ← خاملين) بحد يومي باش ما ناخذوش باند */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120);
                $t->text('message');
                $t->string('status', 12)->default('running'); // running · paused · done
                $t->unsignedInteger('per_day')->default(120);
                $t->unsignedTinyInteger('from_hour')->default(11);
                $t->unsignedTinyInteger('to_hour')->default(21);
                $t->timestamp('next_at')->nullable();
                $t->text('sessions')->nullable(); // الأرقام اللي نبعتو منها (JSON) — كل عميل ياخذ رقم واحد بس
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('campaign_recipients')) {
            Schema::create('campaign_recipients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('campaign_id')->index();
                $t->unsignedBigInteger('customer_id');
                $t->string('segment', 10);   // active · medium · idle
                $t->unsignedInteger('prio')->default(0);
                $t->string('phone', 30);
                $t->string('session_uuid', 40)->nullable();
                $t->string('status', 10)->default('pending'); // pending · sent · failed · skipped
                $t->timestamp('sent_at')->nullable();
                $t->string('error', 190)->nullable();
                $t->unique(['campaign_id', 'customer_id']);
                $t->index(['campaign_id', 'status', 'prio']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
