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
            $table->string('receiving_phone', 15)->nullable();
            $table->string('session_name')->nullable();
        });
        $saved = json_decode(DB::table('settings')->where('id', 1)->value('whatsapp_numbers') ?? '[]', true) ?? [];
        $names = [];
        foreach ($saved as $session) {
            if (is_array($session) && is_string($session['session_uuid'] ?? null) && is_string($session['name'] ?? null)
                && mb_strlen($session['name']) <= 255) {
                $names[$session['session_uuid']] = $session['name'];
            }
        }
        DB::table('whatsapp_messagess')->orderBy('id')->chunkById(500, function ($messages) use ($names) {
            foreach ($messages as $message) {
                // Legacy webhook code used the customer address when metadata was absent.
                $phone = $message->phone !== $message->from
                    ? \App\Services\WhatsAppMessageContext::businessPhone($message->phone) : null;
                DB::table('whatsapp_messagess')->where('id', $message->id)->update([
                    'receiving_phone' => $phone, 'session_name' => $names[$message->session_uuid] ?? null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messagess', fn (Blueprint $table) => $table->dropColumn(['receiving_phone', 'session_name']));
    }
};
