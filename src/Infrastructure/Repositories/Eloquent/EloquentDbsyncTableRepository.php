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
            ->with(['connection', 'columns'])
            ->get();
    }

    public function getForConnection(int $connectionId)
    {
        return DbsyncTable::query()
            ->where('connection_id', $connectionId)
            ->with(['connection', 'columns'])
            ->get();
    }

    public function getForAll()
    {
        return DbsyncTable::query()
            ->with(['connection', 'columns'])
            ->get();
    }
}
