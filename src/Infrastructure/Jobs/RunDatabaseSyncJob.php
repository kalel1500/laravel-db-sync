<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Thehouseofel\Dbsync\Application\DatabaseSyncExecutor;

class RunDatabaseSyncJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected ?int $connectionId = null,
        protected ?int $tableId = null,
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DatabaseSyncExecutor $executor): void
    {
        $executor->execute(
            connectionId: $this->connectionId,
            tableId     : $this->tableId,
        );
    }
}
