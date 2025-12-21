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
        Schema::create('dbsync_column_table', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('dbsync_tables')->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('dbsync_columns')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dbsync_column_table');
    }
};
