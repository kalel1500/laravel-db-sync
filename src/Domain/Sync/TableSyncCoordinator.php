<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

            $message = Str::limit(
                $e->getMessage(),
                10000,
                "\n\n[Truncated: message exceeded 10000 characters]"
            );

            $trace = Str::limit(
                $e->getTraceAsString(),
                100000,
                "\n\n[Truncated: trace exceeded 100000 characters]"
            );

            $run->update([
                'status'        => 'failed',
                'error_message' => $message,
                'error_trace'   => $trace,
                'finished_at'   => now(),
            ]);

            // IMPORTANT: swallow exception, do NOT rethrow
        } finally {
            optional($lock)->release();
        }
    }
}

