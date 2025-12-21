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
        DbsyncTable      $table
    ): void
    {
        $source = DB::connection($connection->source_connection);
        $target = DB::connection($connection->target_connection);

        $rows = $table->source_query
            ? collect($source->select($table->source_query))
            : $source->table($table->source_table)->get();

        if ($rows->count() < $table->min_records) {
            return;
        }

        $rows
            ->chunk($table->batch_size)
            ->each(function ($chunk) use ($target, $table) {
                $target->table($table->target_table)
                    ->insert(
                        $chunk->map(fn($row) => (array)$row)->all()
                    );
            });
    }
}

