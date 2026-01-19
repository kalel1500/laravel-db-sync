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
        Schema::create('dbsync_columns', function (Blueprint $table) {
            $table->id();
            $table->string('method'); // Blueprint method: string, integer, decimal, foreignId, etc.
            $table->json('parameters'); // Parameters: ["name", 100], ["user_id"], etc.
            $table->json('modifiers')->nullable(); // Modifiers: ["nullable", "unique"], [{"method": "...", "parameters": [".."]}], etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dbsync_columns');
    }
};
