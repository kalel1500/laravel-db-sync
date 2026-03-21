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
            $query = $source->table($source->raw('(' . $table->source_query . ') as __dbsync_sub__'));
        } else {
            $columns = $this->resolveTargetColumns($table);
            $query = $source->table($table->source_table)->select($columns);
        }

        $numRows = $query->count();
        if ($numRows < 1 || $numRows < ($table->min_records ?? 1)) {
            return 0;
        }

        $callback = function ($chunk) use ($target, $targetTable, $caseTransforms, $table, &$total) {
            $preparedRows = $this->prepareRows(collect($chunk), $caseTransforms);
            DbsyncSchema::connection($target)->insert($table, $targetTable, $preparedRows);
            $total += count($chunk);
        };

        $resolvedCol = $this->resolvePrimaryKeyColumn($table);
        if ($resolvedCol->method->isChunkById()) {
            $query->chunkById($table->batch_size, $callback, $resolvedCol->name);
        } else {
            $query->orderBy($resolvedCol->name)->chunk($table->batch_size, $callback);
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
     * Resolve the primary key column name to use with chunkById().
     *
     * Resolution order:
     *  1. Column whose method is an auto-increment shorthand (id, increments, bigIncrements…)
     *     OR an integer type with autoIncrement=true as second parameter.
     *  2. Column that has a 'primary' modifier (string or array form).
     *  3. First value of $table->primary_key (composite-key definition).
     *  4. Name of the very first column defined on the table.
     */
    protected function resolvePrimaryKeyColumn(DbsyncTable $table): ResolvedPrimaryDto
    {
        $chunk_config = $table->chunk_config;
        $chunk_column = $chunk_config['column'] ?? null;
        $chunk_method = $chunk_config['method'] ?? null;
        if ($chunk_method) {
            return new ResolvedPrimaryDto($chunk_column, ChunkMethodVo::from($chunk_method));
        }

        $incrementMethods = [
            'id',
            'increments',
            'bigIncrements',
            'mediumIncrements',
            'smallIncrements',
            'tinyIncrements',
        ];

        $integerMethods = [
            'integer',
            'bigInteger',
            'mediumInteger',
            'smallInteger',
            'tinyInteger',
            'unsignedInteger',
            'unsignedBigInteger',
            'unsignedMediumInteger',
            'unsignedSmallInteger',
            'unsignedTinyInteger',
        ];

        $columns = $chunk_column
            ? $table->columns->whereLike('parameters', "%$chunk_column%")->sortBy('pivot.order')
            : $table->columns->sortBy('pivot.order');

        // 1. Método de autoincrement explícito, o entero con segundo parámetro true
        foreach ($columns as $column) {
            $method = $column->method;
            $params = $column->parameters ?? [];

            if (in_array($method, $incrementMethods, true)) {
                // 'id' sin parámetros → columna 'id'; con parámetros → $params[0]
                return new ResolvedPrimaryDto($params[0] ?? 'id', ChunkMethodVo::chunkById);
            }

            if (in_array($method, $integerMethods, true) && ($params[1] ?? false) === true) {
                return new ResolvedPrimaryDto($params[0], ChunkMethodVo::chunkById);
            }
        }

        // 2. Modificador 'primary' en cualquier columna
        foreach ($columns as $column) {
            $params    = $column->parameters ?? [];
            $modifiers = $column->modifiers  ?? [];

            foreach ($modifiers as $modifier) {
                $modMethod = is_array($modifier) ? ($modifier['method'] ?? '') : $modifier;

                if ($modMethod === 'primary') {
                    return new ResolvedPrimaryDto($params[0], ChunkMethodVo::chunkById);
                }
            }
        }

        // 3. Devolver la columna indicada en chunk_config si existe, aunque no cumpla las condiciones anteriores
        if ($chunk_column) {
            return new ResolvedPrimaryDto($chunk_column, ChunkMethodVo::chunk);
        }

        // 4. Primer valor de primary_key compuesta
        if (!empty($table->primary_key[0])) {
            return new ResolvedPrimaryDto($table->primary_key[0], ChunkMethodVo::chunk);
        }

        // 5. Primera columna de la tabla como último recurso
        $first = $columns->first();
        if ($first) {
            return new ResolvedPrimaryDto($first->parameters[0] ?? 'id', ChunkMethodVo::chunk);
        }

        return new ResolvedPrimaryDto('id', ChunkMethodVo::chunk);
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
