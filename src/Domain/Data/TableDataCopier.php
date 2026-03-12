<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

use Illuminate\Support\Facades\DB;
use Thehouseofel\Dbsync\Infrastructure\Facades\DbsyncSchema;
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

        $caseTransforms = $this->resolveCaseTransforms($table);
        $total          = 0;

        if ($table->source_query) {
            // Con source_query no podemos paginar a nivel SQL, pero sí podemos
            // hacer el COUNT antes de cargar todos los datos.
            $count = $source->selectOne(
                'SELECT COUNT(*) as aggregate FROM (' . $table->source_query . ') as __dbsync_count__'
            );
            $numRows = (int)($count->aggregate ?? 0);

            if ($numRows < 1 || $numRows < ($table->min_records ?? 1)) {
                return 0;
            }

            collect($source->select($table->source_query))
                ->chunk($table->batch_size)
                ->each(function ($chunk) use ($target, $targetTable, $caseTransforms, $table, &$total) {
                    $preparedRows = $this->prepareRows($chunk, $caseTransforms);
                    DbsyncSchema::connection($target)->insert($table, $targetTable, $preparedRows);
                    $total += $chunk->count();
                });
        } else {
            $columns = $this->resolveTargetColumns($table);

            $primaryKey = 'id'; // TODO Cambiar por un calculo

            $query = $source
                ->table($table->source_table)
                ->select($columns);

            $numRows = $query->count();

            if ($numRows < 1 || $numRows < ($table->min_records ?? 1)) {
                return 0;
            }

            $query->chunkById($table->batch_size, function ($chunk) use ($target, $targetTable, $caseTransforms, $table, &$total) {
                $preparedRows = $this->prepareRows(collect($chunk), $caseTransforms);
                DbsyncSchema::connection($target)->insert($table, $targetTable, $preparedRows);
                $total += count($chunk);
            }, $primaryKey);
        }

        return $total;
    }

    /**
     * Apply case transforms to a collection of rows and return them as a plain array.
     */
    protected function prepareRows(\Illuminate\Support\Collection $chunk, array $caseTransforms): array
    {
        return $chunk->map(function ($row) use ($caseTransforms) {
            $data = (array)$row;

            foreach ($caseTransforms as $column => $transform) {
                if (
                    array_key_exists($column, $data) &&
                    is_string($data[$column])
                ) {
                    $data[$column] = match ($transform) {
                        'upper' => mb_strtoupper($data[$column]),
                        'lower' => mb_strtolower($data[$column]),
                        default => $data[$column],
                    };
                }
            }

            return $data;
        })->all();
    }

    /**
     * Resolve the list of column names that must be selected from the source table,
     * based on the destination schema definition.
     */
    protected function resolveTargetColumns(DbsyncTable $table): array
    {
        $columns = [];

        foreach ($table->columns->sortBy('pivot.order') as $column) {
            $method = $column->method;
            $params = $column->parameters ?? [];

            // CASO 1: Métodos sin parámetros de nombre (Nombres fijos de Laravel)
            if (in_array($method, ['id', 'timestamps', 'softDeletes', 'rememberToken'])) {
                switch ($method) {
                    case 'id':
                        $columns[] = empty($params) ? 'id' : $params[0];
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
                }
                continue;
            }

            // CASO 2: foreignId y similares (Laravel usa el primer parámetro como nombre de columna)
            if (!empty($params[0]) && is_string($params[0])) {
                $columns[] = $params[0];
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * Resolve case transformation rules per column.
     *
     * Returns: ['column_name' => 'upper|lower']
     */
    protected function resolveCaseTransforms(DbsyncTable $table): array
    {
        $transforms = [];

        foreach ($table->columns as $column) {
            if (empty($column->case_transform)) {
                continue;
            }

            $method     = $column->method;
            $parameters = $column->parameters ?? [];

            $name = match ($method) {
                'id'                                         => 'id',
                'timestamps', 'softDeletes', 'rememberToken' => null,
                default                                      => $parameters[0] ?? null,
            };

            if ($name) {
                $transforms[$name] = $column->case_transform;
            }
        }

        return $transforms;
    }
}
