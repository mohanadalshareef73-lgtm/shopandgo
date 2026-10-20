<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notif_orgs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->onDelete('cascade')->comment('الربط مع نوع المستخدم');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('الربط مع نوع المستخدم');
            $table->boolean('read')->default(false)->comment('حالة التعميم');
            $table->unique(['notification_id', 'user_id'], 'notification_id_user_id_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notif_orgs');
    }
};
