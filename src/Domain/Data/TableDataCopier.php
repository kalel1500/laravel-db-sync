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

        $resolvedStrategy = $this->resolveStrategy($table);

        $callbackChunks = function ($chunk) use ($target, $targetTable, $caseTransforms, $table, &$total) {
            $preparedRows = $this->prepareRows(collect($chunk), $caseTransforms);
            DbsyncSchema::connection($target)->insert($table, $targetTable, $preparedRows);
            $total += count($chunk);
        };

        $callbackCursor = function () use ($query, $target, $targetTable, $caseTransforms, $table, &$total) {
            $batch = [];

            foreach ($query->cursor() as $row) {
                $data    = $this->transformRow((array)$row, $caseTransforms);
                $batch[] = $data;

                if (($batchSize = count($batch)) >= $table->batch_size) {
                    DbsyncSchema::connection($target)->insert($table, $targetTable, $batch);
                    $total += $batchSize;
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                DbsyncSchema::connection($target)->insert($table, $targetTable, $batch);
                $total += count($batch);
            }
        };

        match (true) {
            $resolvedStrategy->isChunkById()   => $query->chunkById($table->batch_size, $callbackChunks, $resolvedStrategy->column),
            $resolvedStrategy->isCursor()      => $callbackCursor(),
            $resolvedStrategy->isChunkOffset() => $query->orderBy($resolvedStrategy->column)->chunk($table->batch_size, $callbackChunks),
        };

        return $total;
    }

    /**
     * Apply case transforms to a collection of rows and return them as a plain array.
     */
    protected function prepareRows(\Illuminate\Support\Collection $chunk, array $caseTransforms): array
    {
        return $chunk->map(fn($row) => $this->transformRow((array)$row, $caseTransforms))->all();
    }

    protected function transformRow(array $data, array $caseTransforms): array
    {
        foreach ($caseTransforms as $column => $transform) {
            if (isset($data[$column]) && is_string($data[$column])) {
                $data[$column] = match ($transform) {
                    'upper' => mb_strtoupper($data[$column]),
                    'lower' => mb_strtolower($data[$column]),
                    default => $data[$column],
                };
            }
        }

        return $data;
    }

    protected function resolveStrategy(DbsyncTable $table): ResolvedStrategyDto
    {
        $strategy = $table->copy_strategy ?? [];
        $strategy = new ConfigStrategyDto(
            type  : isset($strategy['type']) ? CopyStrategyTypeVo::from($strategy['type']) : null,
            column: $strategy['column'] ?? null
        );

        // Prioridad Manual: Si el usuario fuerza un método, manda el usuario.
        if ($strategy->isFullyManual()) {
            return new ResolvedStrategyDto(
                type  : $strategy->type,
                column: $strategy->column,
            );
        }

        // Internal helper
        $hasModifier = function (DbsyncColumn $col, string $name) {
            $modifiers = collect($col->modifiers ?? []);
            return $modifiers->contains(function ($m) use ($name) {
                $method = is_array($m) ? ($m['method'] ?? '') : $m;
                return $method === $name;
            });
        };

        $columns = is_null($strategy->column) ? $table->columns : $table->columns->filter(fn($col) => ($col->parameters[0] ?? null) === $strategy->column);
        $columns = $columns->sortBy('pivot.order');

        if ($strategy->column && $columns->isEmpty()) {
            throw new \InvalidArgumentException("Column {$strategy->column} not found.");
        }

        // --- CHUNK OFFSET sin type (Casos menos seguros) ---

        if ($strategy->isChunkOffset()) {

            // Columnas de Fecha (Muy estables para el orden cronológico)
            $dateMethods = ['timestamp', 'dateTime', 'date', 'timestampTz', 'dateTimeTz', 'timestamps', 'timestampsTz'];
            foreach ($columns as $col) {
                if (in_array($col->method, $dateMethods, true)) {
                    $name = in_array($col->method, ['timestamps', 'timestampsTz']) ? 'created_at' : ($col->parameters[0]);
                    return new ResolvedStrategyDto(
                        type  : CopyStrategyTypeVo::CHUNK_OFFSET,
                        column: $name
                    );
                }
            }

            // Fallback: PK Compuesta o Primera Columna
            $fallbackName = $table->primary_key[0] ?? ($columns->first()?->parameters[0] ?? 'id');

            return new ResolvedStrategyDto(
                type  : CopyStrategyTypeVo::CHUNK_OFFSET,
                column: $fallbackName
            );
        }


        // --- CHUNK BY ID sin type (Máximo rendimiento, No Nullables) ---

        // Shorthands de Incremento (id, bigIncrements...)
        $incrementMethods = ['id', 'increments', 'bigIncrements', 'mediumIncrements', 'smallIncrements', 'tinyIncrements'];
        foreach ($columns as $col) {
            if (in_array($col->method, $incrementMethods, true)) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $col->parameters[0] ?? 'id'
                );
            }
        }

        // Modificador 'primary'
        foreach ($columns as $col) {
            if ($hasModifier($col, 'primary')) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $col->parameters[0]
                );
            }
        }

        // Enteros marcados como autoIncrement explícito ->integer('col', true)
        $integerMethods = ['integer', 'bigInteger', 'mediumInteger', 'smallInteger', 'tinyInteger', 'unsignedInteger', 'unsignedBigInteger', 'unsignedMediumInteger', 'unsignedSmallInteger', 'unsignedTinyInteger',];
        foreach ($columns as $col) {
            if (in_array($col->method, $integerMethods, true) && ($col->parameters[1] ?? false) === true) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $col->parameters[0]
                );
            }
        }

        // String IDs (UUID/ULID) NO Nullables
        foreach ($columns as $col) {
            if (in_array($col->method, ['uuid', 'ulid'], true) && !$hasModifier($col, 'nullable')) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $col->parameters[0] ?? $col->method
                );
            }
        }

        // Modificador 'unique' NO Nullable
        foreach ($columns as $col) {
            if ($hasModifier($col, 'unique') && !$hasModifier($col, 'nullable')) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $col->parameters[0]
                );
            }
        }

        // --- CURSOR (si no ha encontrado una columna para el "isChunkById" y no se ha configurado el "chunk" explícitamente) ---
        return new ResolvedStrategyDto(
            type  : CopyStrategyTypeVo::CURSOR,
            column: null
        );
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
