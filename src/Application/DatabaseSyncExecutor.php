<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Application;

use Thehouseofel\Dbsync\Domain\Sync\DatabaseSyncRunner;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class DatabaseSyncExecutor
{
    public function __construct(
        protected DatabaseSyncRunner $runner
    )
    {
    }

    public function execute(
        ?int $connectionId = null,
        ?int $databaseId = null,
        ?int $tableId = null,
    ): void
    {
        $tables = match (true) {
            $tableId !== null      => $this->tablesForTable($tableId),
            $databaseId !== null   => $this->tablesForDatabase($databaseId),
            $connectionId !== null => $this->tablesForConnection($connectionId),
            default                => $this->tablesForAll(),
        };

        // Full sync
        $this->runner->run($tables);
    }

    protected function tablesForTable(int $tableId)
    {
        return DbsyncTable::query()
            ->whereKey($tableId)
            ->where('active', true)
            ->with('database.connection')
            ->get();
    }

    protected function tablesForDatabase(int $databaseId)
    {
        return DbsyncTable::query()
            ->where('database_id', $databaseId)
            ->where('active', true)
            ->with('database.connection')
            ->get();
    }

    protected function tablesForConnection(int $connectionId)
    {
        return DbsyncTable::query()
            ->whereHas('database', function ($q) use ($connectionId) {
                $q->where('connection_id', $connectionId);
            })
            ->where('active', true)
            ->with('database.connection')
            ->get();
    }

    protected function tablesForAll()
    {
        return DbsyncTable::query()
            ->where('active', true)
            ->with('database.connection')
            ->get();
    }
}
