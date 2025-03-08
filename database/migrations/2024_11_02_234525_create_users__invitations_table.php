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
        Schema::create('users__invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->integer('attempts')->default(1);
            $table->datetime('last_attempt_at')->default(now());
            $table->string('invitationtoken')->nullable();
            $table->boolean('accepted')->nullable();
            $table->datetime('expiration')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users__invitations');
    }
};
