<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            $table->string('operator_status')->default('new')->index();
            $table->text('operator_notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('operator_updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('cart_quotes', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['handled_by']);
            }
            $table->dropIndex(['operator_status']);
            $table->dropColumn(['handled_by', 'operator_status', 'operator_notes', 'operator_updated_at']);
        });
    }
};
