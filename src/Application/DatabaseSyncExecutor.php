<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Application;

use Thehouseofel\Dbsync\Domain\Contracts\DbsyncTableRepository;
use Thehouseofel\Dbsync\Domain\Sync\DatabaseSyncRunner;

class DatabaseSyncExecutor
{
    public function __construct(
        protected DbsyncTableRepository $repositoryTable,
        protected DatabaseSyncRunner    $runner,
    )
    {
    }

    public function execute(
        ?int $connectionId = null,
        ?int $tableId = null,
    ): void
    {
        $tables = match (true) {
            $tableId !== null      => $this->repositoryTable->getForTable($tableId),
            $connectionId !== null => $this->repositoryTable->getForConnection($connectionId),
            default                => $this->repositoryTable->getForAll(),
        };

        // Full sync
        $this->runner->run($tables);
    }
}
