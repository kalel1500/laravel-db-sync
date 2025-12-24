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
        Schema::create('dbsync_tables', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->string('target_table');
            $table->unsignedInteger('min_records')->nullable();
            $table->boolean('active')->default(true);
            $table->longText('source_query')->nullable();
            $table->boolean('drop_before_create')->default(true);
            $table->boolean('truncate_before_insert')->default(true);
            $table->unsignedInteger('batch_size')->default(1000);
            $table->foreignId('database_id')->constrained('dbsync_databases')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dbsync_tables');
    }
};
