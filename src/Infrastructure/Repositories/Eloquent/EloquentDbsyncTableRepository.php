<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Repositories\Eloquent;

use Thehouseofel\Dbsync\Domain\Contracts\DbsyncTableRepository;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class EloquentDbsyncTableRepository implements DbsyncTableRepository
{
    public function getForTable(int $tableId)
    {
        return DbsyncTable::query()
            ->whereKey($tableId)
            ->with('database.connection')
            ->get();
    }

    public function getForDatabase(int $databaseId)
    {
        return DbsyncTable::query()
            ->where('database_id', $databaseId)
            ->with('database.connection')
            ->get();
    }

    public function getForConnection(int $connectionId)
    {
        return DbsyncTable::query()
            ->whereHas('database', function ($q) use ($connectionId) {
                $q->where('connection_id', $connectionId);
            })
            ->with('database.connection')
            ->get();
    }

    public function getForAll()
    {
        return DbsyncTable::query()
            ->with('database.connection')
            ->get();
    }
}
