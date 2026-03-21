<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

use Illuminate\Support\Facades\DB;
use Thehouseofel\Dbsync\Infrastructure\Facades\DbsyncSchema;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncColumn;
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

        DbsyncSchema::connection($source)->disableBuffer();

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
     *  0.   Value chunk_config with method.
     *  1.1. Auto-increment methods (id, increments, bigIncrements…)
     *  1.2. Primary modifier.
     *  1.3. Integer type with autoIncrement=true as second parameter.
     *  1.4. String Ids NO Nullables (UUID, ULID)
     *  1.5. Unique modifier NO Nullable.
     *  2.1. Value chunk_config (without method).
     *  2.2. Date columns.
     *  2.3. Fallback: Composite-key or first column.
     */
    protected function resolvePrimaryKeyColumn(DbsyncTable $table): ResolvedPrimaryDto
    {
        $chunk_config = $table->chunk_config;
        $chunk_column = $chunk_config['column'] ?? null;
        $chunk_method = $chunk_config['method'] ?? null;

        // 0. Prioridad Manual: Si el usuario fuerza un método, manda el usuario.
        if ($chunk_method) {
            return new ResolvedPrimaryDto($chunk_column, ChunkMethodVo::from($chunk_method));
        }

        // Helper interno o lógica repetible
        $hasModifier = function (DbsyncColumn $col, string $name) {
            $modifiers = collect($col->modifiers ?? []);
            return $modifiers->contains(function ($m) use ($name) {
                $method = is_array($m) ? ($m['method'] ?? '') : $m;
                return $method === $name;
            });
        };

        $columns = $chunk_column
            ? $table->columns->whereLike('parameters', "%$chunk_column%")->sortBy('pivot.order')
            : $table->columns->sortBy('pivot.order');

        // --- ESTRATO 1: CHUNK BY ID (Máximo rendimiento, No Nullables) ---

        // 1.1 Shorthands de Incremento (id, bigIncrements...)
        $incrementMethods = ['id', 'increments', 'bigIncrements', 'mediumIncrements', 'smallIncrements', 'tinyIncrements'];
        foreach ($columns as $col) {
            if (in_array($col->method, $incrementMethods, true)) {
                return new ResolvedPrimaryDto($col->parameters[0] ?? 'id', ChunkMethodVo::chunkById);
            }
        }

        // 1.2 Modificador 'primary'
        foreach ($columns as $col) {
            if ($hasModifier($col, 'primary')) {
                return new ResolvedPrimaryDto($col->parameters[0], ChunkMethodVo::chunkById);
            }
        }

        // 1.3 Enteros marcados como autoIncrement explícito ->integer('col', true)
        $integerMethods = ['integer', 'bigInteger', 'mediumInteger', 'smallInteger', 'tinyInteger', 'unsignedInteger', 'unsignedBigInteger', 'unsignedMediumInteger', 'unsignedSmallInteger', 'unsignedTinyInteger',];
        foreach ($columns as $col) {
            if (in_array($col->method, $integerMethods, true) && ($col->parameters[1] ?? false) === true) {
                return new ResolvedPrimaryDto($col->parameters[0], ChunkMethodVo::chunkById);
            }
        }

        // 1.4 String IDs (UUID/ULID) NO Nullables
        foreach ($columns as $col) {
            if (in_array($col->method, ['uuid', 'ulid'], true) && !$hasModifier($col, 'nullable')) {
                return new ResolvedPrimaryDto($col->parameters[0] ?? $col->method, ChunkMethodVo::chunkById);
            }
        }

        // 1.5 Modificador 'unique' NO Nullable
        foreach ($columns as $col) {
            if ($hasModifier($col, 'unique') && !$hasModifier($col, 'nullable')) {
                return new ResolvedPrimaryDto($col->parameters[0], ChunkMethodVo::chunkById);
            }
        }

        // --- ESTRATO 2: CHUNK NORMAL (Offset/OrderBy, Casos menos seguros) ---

        // 2.1 Columna indicada en chunk_config pero sin método (Fallback de usuario)
        if ($chunk_column) {
            return new ResolvedPrimaryDto($chunk_column, ChunkMethodVo::chunk);
        }

        // 2.2 Columnas de Fecha (Muy estables para el orden cronológico)
        $dateMethods = ['timestamp', 'dateTime', 'date', 'timestampTz', 'dateTimeTz', 'timestamps', 'timestampsTz'];
        foreach ($columns as $col) {
            if (in_array($col->method, $dateMethods, true)) {
                $name = in_array($col->method, ['timestamps', 'timestampsTz']) ? 'created_at' : ($col->parameters[0]);
                return new ResolvedPrimaryDto($name, ChunkMethodVo::chunk);
            }
        }

        // 2.3 Fallback Final: PK Compuesta o Primera Columna
        $fallbackName = $table->primary_key[0] ?? ($columns->first()?->parameters[0] ?? 'id');

        return new ResolvedPrimaryDto($fallbackName, ChunkMethodVo::chunk);
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
