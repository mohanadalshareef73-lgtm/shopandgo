<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** سجل التعديلات: كل إضافة / تعديل / مسح، مين وامتى وشن تبدل بالضبط */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->string('user_type', 20)->default('user'); // user | customer | system
                $t->string('user_name', 120)->nullable();
                $t->string('action', 10)->index();            // created | updated | deleted
                $t->string('model', 60)->index();
                $t->unsignedBigInteger('model_id')->nullable()->index();
                $t->string('label', 150)->nullable()->index();
                $t->text('changes')->nullable();               // JSON: {field: [old, new]}
                $t->string('ip', 45)->nullable();
                $t->string('url', 255)->nullable();
                $t->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
