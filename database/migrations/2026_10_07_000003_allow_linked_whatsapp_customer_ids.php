<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // These legacy phone columns also retain a Whatsy @lid address verbatim.
    private const COLUMNS = [
        ['cart_quotes', 'whatsapp_customer_phone', 'NULL'],
        ['cart_whatsapp_button_events', 'customer_phone', 'NOT NULL'],
        ['cart_status_audits', 'actor_phone', 'NULL'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (self::COLUMNS as [$table, $column, $nullable]) {
                DB::statement("ALTER TABLE `$table` MODIFY `$column` VARCHAR(64) $nullable");
            }
        }
        // SQLite does not enforce VARCHAR lengths and needs no schema alteration.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (self::COLUMNS as [$table, $column]) {
                if (DB::table($table)->whereRaw("CHAR_LENGTH(`$column`) > 15")->exists()) {
                    throw new RuntimeException('Cannot narrow WhatsApp address columns while linked IDs are stored.');
                }
            }
            foreach (self::COLUMNS as [$table, $column, $nullable]) {
                DB::statement("ALTER TABLE `$table` MODIFY `$column` VARCHAR(15) $nullable");
            }
        }
    }
};
