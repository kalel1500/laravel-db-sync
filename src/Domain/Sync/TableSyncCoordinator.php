<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTableRun;

class TableSyncCoordinator
{
    public function __construct(
        protected TableSynchronizer $synchronizer
    ) {}

    public function handle(
        DbsyncConnection $connection,
        DbsyncTable $table
    ): void {

        $run = DbsyncTableRun::create([
            'connection_id' => $connection->id,
            'table_id'      => $table->id,
            'status'        => 'running',
            'started_at'    => now(),
        ]);

        $lock = Cache::lock("dbsync:table:{$table->id}", 600);

        try {
            if (! $lock->get()) {
                throw new \RuntimeException('Table is already being synced.');
            }

            $rows = $this->synchronizer->sync($connection, $table);

            $run->update([
                'status'        => 'success',
                'rows_copied'   => $rows,
                'finished_at'   => now(),
            ]);

        } catch (\Throwable $e) {

            try {
                $message = Str::limit(
                    $e->getMessage(),
                    10_000,
                    "\n\n[Truncated: message exceeded 10_000 characters]"
                );

                $trace = Str::limit(
                    $e->getTraceAsString(),
                    100_000,
                    "\n\n[Truncated: trace exceeded 100_000 characters]"
                );

                $run->update([
                    'status'        => 'failed',
                    'error_message' => $message,
                    'error_trace'   => $trace,
                    'finished_at'   => now(),
                ]);
            } catch (\Throwable $loggingException) {
                Log::critical('Failed to store dbsync error', [
                    'original_exception' => $e,
                    'logging_exception' => $loggingException,
                ]);
            }

            // IMPORTANT: swallow exception, do NOT rethrow
        } finally {
            optional($lock)->release();
        }
    }
}

