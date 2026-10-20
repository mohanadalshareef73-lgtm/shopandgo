<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // ✅ Add order_type if not exists
            if (!Schema::hasColumn('orders', 'order_type')) {
                $table->string('order_type')->nullable()->after('status')->comment('نوع الطلب');
            }

            // ✅ Add delivery_type if not exists
            if (!Schema::hasColumn('orders', 'delivery_type')) {
                $table->enum('delivery_type', ['توصيل شخصي', 'توصيل عن طريق مندوب', 'توصيل عن طريق مكتب'])
                      ->nullable()
                      ->after('order_type')
                      ->comment('طريقة التوصيل');
            }

            // ✅ Add delivery_name if not exists
            if (!Schema::hasColumn('orders', 'delivery_name')) {
                $table->string('delivery_name')->nullable()->after('delivery_type')->comment('اسم المندوب أو المكتب');
            }

            if (!Schema::hasColumn('orders', 'storage_num')) {
                $table->string('storage_num')->nullable()->comment('رقم التخزين');
            }

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_type')) {
                $table->dropColumn('order_type');
            }
            if (Schema::hasColumn('orders', 'delivery_type')) {
                $table->dropColumn('delivery_type');
            }
            if (Schema::hasColumn('orders', 'delivery_name')) {
                $table->dropColumn('delivery_name');
            }
        });
    }
};
