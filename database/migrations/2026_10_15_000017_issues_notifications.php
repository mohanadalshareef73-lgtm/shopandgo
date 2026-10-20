<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** مشاكل الطلبات (نواقص / لم يصل / أخرى) + إشعارات حساب العميل */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_issues')) {
            Schema::create('order_issues', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('order_id')->index();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->string('type', 20);                   // missing · not_arrived · damaged · other
                $t->unsignedInteger('qty')->nullable();
                $t->decimal('value', 12, 2)->nullable();
                $t->string('action', 20)->nullable();     // deduct · credit · refund · later
                $t->text('note')->nullable();
                $t->string('status', 20)->default('open'); // open · resolved
                $t->text('resolution')->nullable();
                $t->unsignedBigInteger('created_id')->nullable();
                $t->unsignedBigInteger('resolved_by')->nullable();
                $t->timestamp('resolved_at')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('customer_messages')) {
            Schema::table('customer_messages', function (Blueprint $t) {
                if (!Schema::hasColumn('customer_messages', 'kind')) {
                    $t->string('kind', 30)->nullable();
                }
                if (!Schema::hasColumn('customer_messages', 'order_id')) {
                    $t->unsignedBigInteger('order_id')->nullable();
                }
            });
        }
        if (Schema::hasTable('whatsapp_messages') && !DB::table('whatsapp_messages')->where('type', 'order_issue')->exists()) {
            $row = ['type' => 'order_issue', 'message' => "مرحباً {الاسم}\nبخصوص طلبك {رقم_الإيصال}: {المشكلة}\n{الإجراء}\nنعتذر عن الإزعاج، وسنتابع معك. Shop & Go", 'created_at' => now(), 'updated_at' => now()];
            if (Schema::hasColumn('whatsapp_messages', 'title')) {
                $row['title'] = 'مشكلة في طلب';
            }
            if (Schema::hasColumn('whatsapp_messages', 'enabled')) {
                $row['enabled'] = true;
            }
            DB::table('whatsapp_messages')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_issues');
    }
};
