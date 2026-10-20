<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** قائمة الحجز داخل المنظومة: متى تحجزت السلة فعلياً ومن حجزها */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cart_quotes', function (Blueprint $t) {
            if (!Schema::hasColumn('cart_quotes', 'booked_done_at')) {
                $t->timestamp('booked_done_at')->nullable();
            }
            if (!Schema::hasColumn('cart_quotes', 'booked_by')) {
                $t->unsignedBigInteger('booked_by')->nullable();
            }
        });
        // السلات القديمة المتأكدة نعتبروها تحجزت، باش القائمة تبدا نظيفة
        \Illuminate\Support\Facades\DB::table('cart_quotes')->where('portal_status', 'confirmed')->whereNull('booked_done_at')
            ->where('confirmed_at', '<', now()->subDay())->update(['booked_done_at' => now()]);
    }

    public function down(): void
    {
    }
};
