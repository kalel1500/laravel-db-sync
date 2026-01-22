<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

use Illuminate\Support\Facades\DB;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class TableDataCopier
{
    public function copy(
        DbsyncConnection $connection,
        DbsyncTable      $table,
    ): int
    {
        return $this->copyToTarget($connection, $table, $table->target_table);
    }

    public function copyToTarget(
        DbsyncConnection $connection,
        DbsyncTable      $table,
        string           $targetTable,
    ): int
    {
        $source = DB::connection($connection->source_connection);
        $target = DB::connection($connection->target_connection);

        if ($table->source_query) {
            $rows = collect($source->select($table->source_query));
        } else {
            $columns = $this->resolveTargetColumns($table);

            $rows = $source
                ->table($table->source_table)
                ->select($columns)
                ->get();
        }

        $numRows = $rows->count();
        if ($numRows < 1 || $numRows < ($table->min_records ?? 1)) {
            return 0;
        }

        return $rows
            ->chunk($table->batch_size)
            ->reduce(function (int $total, $chunk) use ($target, $targetTable) {
                $target->table($targetTable)->insert(
                    $chunk->map(fn($row) => (array)$row)->all()
                );

                return $total + $chunk->count();
            }, 0);
    }

    /**
     * Resolve the list of column names that must be selected from the source table,
     * based on the destination schema definition.
     */
    protected function resolveTargetColumns(DbsyncTable $table): array
    {
        $columns = [];

        foreach ($table->columns->sortBy('pivot.order') as $column) {
            $method     = $column->method;
            $parameters = $column->parameters ?? [];

            switch ($method) {
                case 'id':
                    $columns[] = 'id';
                    break;

                case 'timestamps':
                    $columns[] = 'created_at';
                    $columns[] = 'updated_at';
                    break;

                case 'softDeletes':
                    $columns[] = 'deleted_at';
                    break;

                case 'rememberToken':
                    $columns[] = 'remember_token';
                    break;

                default:
                    if (! empty($parameters[0])) {
                        $columns[] = $parameters[0];
                    }
                    break;
            }
        }

        return array_values(array_unique($columns));
    }
}
