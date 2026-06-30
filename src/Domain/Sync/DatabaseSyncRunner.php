<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class DatabaseSyncRunner
{
    public function __construct(
        protected TableSyncCoordinator $tableCoordinator
    )
    {
    }

    /**
     * @param iterable<DbsyncTable> $tables
     */
    public function run(iterable $tables, bool $force): void
    {
        foreach ($tables as $table) {
            $connection = $table->connection;

            if (! $force && (! $connection->active || ! $table->active)) {
                continue;
            }

            $this->tableCoordinator->handle(
                $connection,
                $table
            );
        }
    }
}
