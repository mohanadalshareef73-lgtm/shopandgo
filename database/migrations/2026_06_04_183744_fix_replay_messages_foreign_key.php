<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support altering foreign keys — skip on SQLite (tests)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('replay_messages', function (Blueprint $table) {
            try {
                $table->dropForeign(['whatsapp_messages_id']);
            } catch (\Exception $e) {
                // Already removed or never existed — safe to ignore
            }

            $table->foreign('whatsapp_messages_id')
                ->references('id')
                ->on('whatsapp_messagess') // correct double-s table name
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('replay_messages', function (Blueprint $table) {
            try {
                $table->dropForeign(['whatsapp_messages_id']);
            } catch (\Exception $e) {
                // Ignore
            }
        });
    }
};
