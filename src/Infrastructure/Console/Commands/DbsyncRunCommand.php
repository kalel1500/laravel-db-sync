<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Thehouseofel\Dbsync\Infrastructure\Jobs\RunDatabaseSyncJob;

class DbsyncRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dbsync:run
                            {--connection= : The ID of the connection to sync}
                            {--table= : The ID of the table to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run database synchronization. Optionally specify connection or table ID to limit the scope.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $connectionId = $this->option('connection');
        $tableId      = $this->option('table');

        dispatch_sync(new RunDatabaseSyncJob(
            connectionId: is_null($connectionId) ? null : (int)$connectionId,
            tableId     : is_null($tableId) ? null : (int)$tableId
        ));
    }
}
