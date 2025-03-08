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
        Schema::connection('sqlite')->create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('db_connection')->default('pgsql');
            $table->string('name')->nullable();
            $table->string('db_host')->nullable();
            $table->integer('db_port')->default(5432);
            $table->string('db_username')->nullable();
            $table->string('db_password')->nullable();
            $table->boolean('choosable_for_new_clients')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::connection('sqlite')->dropIfExists('servers');
    }
};
