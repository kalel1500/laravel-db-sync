<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Thehouseofel\Dbsync\Domain\Sync\DatabaseSyncRunner;

class RunDatabaseSyncJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DatabaseSyncRunner $runner): void
    {
        $runner->run();
    }
}
