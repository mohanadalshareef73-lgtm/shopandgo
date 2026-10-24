<?php

use Illuminate\Database\Migrations\Migration;

/** 🚫 وقف طلب التوصيل من الزبون (رابط الواتساب + حسابه). يرجع يتفعل من الإعدادات لو احتجتوه */
return new class extends Migration {
    public function up(): void
    {
        try {
            \App\Services\AppSettings::put(['portal_delivery_enabled' => false]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
    }
};
