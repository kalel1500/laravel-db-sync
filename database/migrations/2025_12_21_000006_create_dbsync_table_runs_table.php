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
        Schema::create('dbsync_table_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('dbsync_connections')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('dbsync_tables')->cascadeOnDelete();
            $table->string('status', 20)->comment('pending | running | success | failed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('rows_copied')->nullable();
            $table->longText('error_message')->nullable();
            $table->longText('error_trace')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['table_id', 'status']);
            $table->index(['started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dbsync_table_runs');
    }
};
