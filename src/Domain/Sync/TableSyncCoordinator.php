<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Thehouseofel\Dbsync\Domain\Strategies\AlwaysRecreateStrategy;
use Thehouseofel\Dbsync\Domain\Strategies\CompareAndOptimizeStrategy;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncDatabase;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class TableSyncCoordinator
{
    public function __construct(
        protected AlwaysRecreateStrategy $alwaysRecreate,
        protected CompareAndOptimizeStrategy $compareAndOptimize
    ) {}

    public function handle(
        DbsyncConnection $connection,
        DbsyncDatabase $database,
        DbsyncTable $table
    ): void {
        // De momento, fijo:
        $strategy = $this->alwaysRecreate;

        // Más adelante:
        // $strategy = $table->strategy === 'compare'
        //     ? $this->compareAndOptimize
        //     : $this->alwaysRecreate;

        $strategy->sync($connection, $database, $table);
    }
}

