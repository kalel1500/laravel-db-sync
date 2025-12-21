<?php

namespace Thehouseofel\Dbsync\Domain\Contracts;

use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncDatabase;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

interface SyncStrategy
{
    public function sync(
        DbsyncConnection $connection,
        DbsyncDatabase $database,
        DbsyncTable $table
    ): int;
}
