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
        Schema::create('dbsync_databases', function (Blueprint $table) {
            $table->id();
            $table->string('source_database');
            $table->string('target_database');
            $table->string('source_schema')->nullable();
            $table->string('target_schema')->nullable();
            $table->foreignId('connection_id')->constrained('dbsync_connections')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dbsync_databases');
    }
};
