<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 🔕 إيقاف رسالة «طلبك دخل رحلة» (trip_added) — تقدر ترجّعها من إعدادات الرسائل */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whatsapp_messages') && Schema::hasColumn('whatsapp_messages', 'enabled')) {
            DB::table('whatsapp_messages')->where('type', 'trip_added')->update(['enabled' => false]);
        }
    }

    public function down()
    {
    }
};
