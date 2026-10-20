<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('shein_company_percentage', 8, 4)->default(0);
            $table->text('shein_received_message')->nullable();
            $table->text('shein_completed_message')->nullable();
            $table->text('shein_calculation_note')->nullable();
        });
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->json('quotation')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_access_token', 64)->nullable()->unique();
            $table->timestamp('pdf_expires_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->dropUnique(['pdf_access_token']);
            $table->dropColumn(['quotation', 'pdf_path', 'pdf_access_token', 'pdf_expires_at']);
        });
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn([
            'shein_company_percentage', 'shein_received_message', 'shein_completed_message', 'shein_calculation_note',
        ]));
    }
};
