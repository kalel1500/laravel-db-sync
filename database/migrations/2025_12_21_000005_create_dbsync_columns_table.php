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
            $table->string('name');
            $table->string('method'); // Blueprint method: string, integer, decimal, etc.
            $table->json('parameters')->nullable(); // Parameters: [20], [8,2], etc.
            $table->json('modifiers')->nullable(); // Modifiers: nullable, unsigned, index, unique, etc.
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
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
