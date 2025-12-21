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
    protected $signature = 'dbsync:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        dispatch_sync(new RunDatabaseSyncJob());
    }
}
