<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Application;

use Thehouseofel\Dbsync\Domain\Sync\DatabaseSyncRunner;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class DatabaseSyncExecutor
{
    public function __construct(
        protected DatabaseSyncRunner $runner
    ) {}

    public function execute(
        ?int $connectionId = null,
        ?int $databaseId = null,
        ?int $tableId = null
    ): void {
        if ($tableId !== null) {
            $this->runForTable($tableId);
            return;
        }

        if ($databaseId !== null) {
            $this->runForDatabase($databaseId);
            return;
        }

        if ($connectionId !== null) {
            $this->runForConnection($connectionId);
            return;
        }

        // Full sync
        $this->runner->run();
    }

    protected function runForTable(int $tableId): void
    {
        $tables = DbsyncTable::query()
            ->whereKey($tableId)
            ->where('active', true)
            ->with('database.connection')
            ->get();

        $this->runner->run($tables);
    }

    protected function runForDatabase(int $databaseId): void
    {
        $tables = DbsyncTable::query()
            ->where('database_id', $databaseId)
            ->where('active', true)
            ->with('database.connection')
            ->get();

        $this->runner->run($tables);
    }

    protected function runForConnection(int $connectionId): void
    {
        $tables = DbsyncTable::query()
            ->whereHas('database', function ($q) use ($connectionId) {
                $q->where('connection_id', $connectionId);
            })
            ->where('active', true)
            ->with('database.connection')
            ->get();

        $this->runner->run($tables);
    }
}
