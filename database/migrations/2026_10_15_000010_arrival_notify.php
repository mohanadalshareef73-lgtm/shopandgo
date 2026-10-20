<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** رسالة "وصلت بضاعتك" تُرسل بعد مراجعة بيانات الاستلام */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'arrived_notified_at')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->timestamp('arrived_notified_at')->nullable();
            });
            // الطلبات المستلمة سابقاً تُعتبر مُبلّغة حتى لا تُرسل لها رسائل مكررة
            DB::table('orders')->where('arrived', true)->update(['arrived_notified_at' => DB::raw('COALESCE(received_at, updated_at)')]);
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        if (Schema::hasColumn('orders', 'arrived_notified_at')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('arrived_notified_at'));
        }
        Schema::enableForeignKeyConstraints();
    }
};
