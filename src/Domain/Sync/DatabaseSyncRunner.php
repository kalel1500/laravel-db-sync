<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;

class DatabaseSyncRunner
{
    public function __construct(
        protected TableSyncCoordinator $tableCoordinator
    )
    {
    }

    public function run(): void
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

            foreach ($database->tables as $table) {
                if (! $table->active) {
                    continue;
                }

                $this->tableCoordinator->handle(
                    $connection,
                    $database,
                    $table
                );
            }
        }
    }
}
