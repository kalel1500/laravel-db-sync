<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncDatabase;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTableRun;

class TableSyncCoordinator
{
    public function __construct(
        protected TableSynchronizer $synchronizer
    ) {}

    public function handle(
        DbsyncConnection $connection,
        DbsyncDatabase $database,
        DbsyncTable $table
    ): void {

        $run = DbsyncTableRun::create([
            'connection_id' => $connection->id,
            'database_id'   => $database->id,
            'table_id'      => $table->id,
            'status'        => 'running',
            'started_at'    => now(),
        ]);

        $lock = Cache::lock("dbsync:table:{$table->id}", 600);

        try {
            if (! $lock->get()) {
                throw new \RuntimeException('Table is already being synced.');
            }

            $rows = $this->synchronizer->sync($connection, $database, $table);

            $run->update([
                'status'        => 'success',
                'rows_copied'   => $rows,
                'finished_at'   => now(),
            ]);

        } catch (\Throwable $e) {
            $run->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'error_trace'   => $e->getTraceAsString(),
                'finished_at'   => now(),
            ]);

            // IMPORTANT: swallow exception, do NOT rethrow
        } finally {
            optional($lock)->release();
        }
    }
}

