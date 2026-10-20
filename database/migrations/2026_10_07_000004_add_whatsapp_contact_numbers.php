<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messagess', function (Blueprint $table) {
            $table->string('contact_phone', 15)->nullable();
            $table->timestamp('contact_lookup_after')->nullable()->index();
        });
        Schema::table('cart_whatsapp_button_events', fn (Blueprint $table) => $table->string('contact_phone', 15)->nullable());
        DB::table('whatsapp_messagess')->where('from', 'like', '%@lid')->whereNotNull('session_uuid')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('cart_quotes')
                    ->whereColumn('cart_quotes.whatsapp_messages_id', 'whatsapp_messagess.id');
            })->update(['contact_lookup_after' => now()]);
    }

    public function down(): void
    {
        Schema::table('cart_whatsapp_button_events', fn (Blueprint $table) => $table->dropColumn('contact_phone'));
        Schema::table('whatsapp_messagess', function (Blueprint $table) {
            $table->dropIndex(['contact_lookup_after']);
            $table->dropColumn(['contact_phone', 'contact_lookup_after']);
        });
    }
};
