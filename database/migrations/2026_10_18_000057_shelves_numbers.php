<?php

use Illuminate\Database\Migrations\Migration;

/** أماكن الرفوف أرقام من 1 لين 15 (بدون حروف) — إعداد بس، أماكن الطلبيات القديمة تقعد زي ما هي */
return new class extends Migration
{
    public function up()
    {
        try {
            \App\Services\AppSettings::put(['shelf_rows' => [], 'shelf_per_row' => 15]);
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
    }
};
