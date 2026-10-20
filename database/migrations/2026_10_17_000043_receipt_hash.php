<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** بصمة صورة إيصال التحويل: نكشفو نفس الصورة لو استعملت أكثر من مرة */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('cart_quotes', 'receipt_hash')) {
            Schema::table('cart_quotes', fn (Blueprint $t) => $t->string('receipt_hash', 64)->nullable()->index());
        }
        // رقم إيصال المصرف (رقم العملية) — هو اللي يفرّق بين إيصال وإيصال
        if (!Schema::hasColumn('cart_quotes', 'receipt_ref')) {
            Schema::table('cart_quotes', fn (Blueprint $t) => $t->string('receipt_ref', 60)->nullable()->index());
        }
    }

    public function down(): void
    {
    }
};
