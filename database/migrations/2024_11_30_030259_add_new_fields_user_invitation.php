<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users__invitations', function (Blueprint $table) {
            $table->integer('sender_id');
            $table->string('sender_name');
            $table->string('sender_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users__invitations', function (Blueprint $table) {
            $table->dropColumn('sender_id');
            $table->dropColumn('sender_name');
            $table->dropColumn('sender_email');
        });
    }
};
