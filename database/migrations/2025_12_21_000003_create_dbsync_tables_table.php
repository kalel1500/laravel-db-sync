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
            $table->string('source_table')->nullable();
            $table->string('target_table');
            $table->unsignedInteger('min_records')->nullable();
            $table->boolean('active')->default(true);
            $table->longText('source_query')->nullable();
            $table->boolean('use_temporal_table');
            $table->unsignedInteger('batch_size')->default(1000);
            $table->json('copy_strategy')->nullable(); // Column to use for chunking. {type: chunkById, "column": "id_user"}
            $table->boolean('has_large_text_values_in_oracle')->default(false);
            $table->json('primary_key')->nullable();
            $table->json('unique_keys')->nullable();
            $table->json('indexes')->nullable();
            $table->foreignId('connection_id')->constrained('dbsync_connections')->cascadeOnDelete();
            $table->unique(['connection_id', 'target_table']);
            $table->index(['active']);
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
