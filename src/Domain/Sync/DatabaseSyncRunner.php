<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class DatabaseSyncRunner
{
    public function __construct(
        protected TableSyncCoordinator $tableCoordinator
    )
    {
    }

    public function run(?iterable $tables = null): void
    {
        if (is_null($tables)) {
            $this->runAll();
            return;
        }

        $this->syncTables($tables);
    }

    protected function runAll(): void
    {
        DbsyncConnection::query()
            ->where('active', true)
            ->with(['databases.tables.columns'])
            ->each(function (DbsyncConnection $connection) {
                $this->syncConnection($connection);
            });
    }

    protected function syncConnection(DbsyncConnection $connection): void
    {
        foreach ($connection->databases as $database) {
            if (! $database->active) {
                continue;
            }

            $this->syncTables($database->tables);
        }
    }

    /**
     * @param iterable<DbsyncTable> $tables
     */
    protected function syncTables(iterable $tables): void
    {
        foreach ($tables as $table) {
            if (! $table->active) {
                continue;
            }

            $this->tableCoordinator->handle(
                $table->database->connection,
                $table->database,
                $table
            );
        }
    }
}
