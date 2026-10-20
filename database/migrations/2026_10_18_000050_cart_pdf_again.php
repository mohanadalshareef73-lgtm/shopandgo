<?php

use Illuminate\Database\Migrations\Migration;

/** سعر السلة على الواتساب يرجع PDF (بدل الرابط) — إعداد بس، ما فيش أي بيانات تتمس */
return new class extends Migration
{
    public function up()
    {
        try {
            \App\Services\AppSettings::put(['wa_quote_link_mode' => false]);
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
    }
};
