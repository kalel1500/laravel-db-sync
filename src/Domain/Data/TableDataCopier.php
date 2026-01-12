<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

use Illuminate\Support\Facades\DB;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncDatabase;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class TableDataCopier
{
    public function copy(
        DbsyncConnection $connection,
        DbsyncDatabase   $database,
        DbsyncTable      $table,
    ): int
    {
        return $this->copyToTarget($connection, $database, $table, $table->target_table);
    }

    public function copyToTarget(
        DbsyncConnection $connection,
        DbsyncDatabase   $database,
        DbsyncTable      $table,
        string           $targetTable,
    ): int
    {
        $source = DB::connection($connection->source_connection);
        $target = DB::connection($connection->target_connection);

        $rows = $table->source_query
            ? collect($source->select($table->source_query))
            : $source->table($table->source_table)->get();

        $numRows = $rows->count();
        if (($numRows < 1) || $numRows < ($table->min_records ?? 1)) {
            return 0;
        }

        return $rows
            ->chunk($table->batch_size)
            ->reduce(function ($total, $chunk) use ($target, $targetTable) {
                $target->table($targetTable)
                    ->insert(
                        $chunk->map(fn($row) => (array)$row)->all()
                    );
                return $total + $chunk->count();
            }, 0);
    }
}

