<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $columnsMeta = $this->resolveColumnsMeta($table);
        $context     = new RowProcessingContext(
            insertableColumns: $this->resolveInsertableColumns($columnsMeta),
            caseTransforms   : $this->resolveCaseTransforms($columnsMeta),
            virtualGenerators: $this->resolveVirtualGenerators($columnsMeta),
        );
        $total       = 0;

        if ($table->source_query) {
            $query = $source->table($source->raw('(' . $table->source_query . ') as __dbsync_sub__'));
        } else {
            $columns = $this->resolveSelectColumns($columnsMeta);
            $query   = $source->table($table->source_table)->select($columns);
        }

        if ($minRecords = $table->min_records) {
            if ($query->take($minRecords)->get()->count() < $minRecords) {
                return 0;
            }
        }

        $resolvedStrategy = $this->resolveStrategy($table, $columnsMeta);

        $callbackChunks = function ($chunk) use ($target, $targetTable, $context, $table, &$total) {
            $preparedRows = $this->prepareRows(collect($chunk), $context);
            DbsyncSchema::connection($target)->insert($table, $targetTable, $preparedRows);
            $total += count($chunk);
        };

        $callbackCursor = function () use ($query, $target, $targetTable, $context, $table, &$total) {
            $batch = [];

            foreach ($query->cursor() as $row) {
                $data    = $this->transformRow((array)$row, $context);
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
    protected function prepareRows(Collection $chunk, RowProcessingContext $context): array
    {
        return $chunk->map(fn($row) => $this->transformRow((array)$row, $context))->all();
    }

    protected function transformRow(array $data, RowProcessingContext $context): array
    {
        // Generadores virtuales
        foreach ($context->virtualGenerators as $column => $type) {
            if (! array_key_exists($column, $data)) {
                $data[$column] = match ($type) {
                    'uuid' => (string)Str::uuid(),
                    'ulid' => (string)Str::ulid(),
                };
            }
        }

        // Case transforms
        foreach ($context->caseTransforms as $column => $transform) {
            if (isset($data[$column]) && is_string($data[$column])) {
                $data[$column] = match ($transform) {
                    'upper' => mb_strtoupper($data[$column]),
                    'lower' => mb_strtolower($data[$column]),
                    default => $data[$column],
                };
            }
        }

        // Filtrado final !! Si source !== table → transformRow DEBE generar el valor
        return array_intersect_key($data, array_flip($context->insertableColumns));
    }

    protected function resolveStrategy(DbsyncTable $table, array $columnsMeta): ResolvedStrategyDto
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

        $columns = collect($columnsMeta)->filter(fn($meta) => $meta['source'] === SourceVo::table->value);
        if ($strategy->column) {
            $columns = $columns->filter(fn($meta) => $meta['name'] === $strategy->column);

            if ($columns->isEmpty()) {
                throw new \InvalidArgumentException("Column {$strategy->column} not found.");
            }
        }

        // --- CHUNK OFFSET sin type (Casos menos seguros) ---

        if ($strategy->isChunkOffset()) {

            // Columnas de Fecha (Muy estables para el orden cronológico)
            $dateMethods = ['timestamp', 'dateTime', 'date', 'timestampTz', 'dateTimeTz', 'timestamps', 'timestampsTz'];
            foreach ($columns as $meta) {
                if (in_array($meta['method'], $dateMethods, true)) {
                    return new ResolvedStrategyDto(
                        type  : CopyStrategyTypeVo::CHUNK_OFFSET,
                        column: $meta['name']
                    );
                }
            }

            // Fallback: PK Compuesta o Primera Columna
            $fallbackName = $table->primary_key[0] ?? $columns->first()['name'] ?? 'id';

            return new ResolvedStrategyDto(
                type  : CopyStrategyTypeVo::CHUNK_OFFSET,
                column: $fallbackName
            );
        }


        // --- CHUNK BY ID sin type (Máximo rendimiento, No Nullables) ---

        // auto_increment
        foreach ($columns as $meta) {
            if ($meta['is_auto_increment']) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $meta['name']
                );
            }
        }

        // primary
        foreach ($columns as $meta) {
            if ($meta['is_primary']) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $meta['name']
                );
            }
        }

        // UUID/ULID
        foreach ($columns as $meta) {
            if (in_array($meta['method'], ['uuid', 'ulid'], true) && !$meta['is_nullable']) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $meta['name']
                );
            }
        }

        // unique
        foreach ($columns as $meta) {
            if ($meta['is_unique'] && !$meta['is_nullable']) {
                return new ResolvedStrategyDto(
                    type  : CopyStrategyTypeVo::CHUNK_BY_ID,
                    column: $meta['name']
                );
            }
        }

        // --- CURSOR (si no ha encontrado una columna para el "isChunkById" y no se ha configurado el "chunk" explícitamente) ---
        return new ResolvedStrategyDto(
            type  : CopyStrategyTypeVo::CURSOR,
            column: null
        );
    }

    protected function resolveColumnsMeta(DbsyncTable $table): array
    {
        $columns = [];

        foreach ($table->columns->sortBy('pivot.order') as $column) {

            $method     = $column->method;
            $params     = $column->parameters;
            $firstParam = $params[0] ?? null;

            $names = [];

            switch ($method) {
                case 'id':
                    $names[] = $firstParam ?? 'id';
                    break;

                case 'timestamps':
                    $names[] = 'created_at';
                    $names[] = 'updated_at';
                    break;

                case 'softDeletes':
                    $names[] = 'deleted_at';
                    break;

                case 'rememberToken':
                    $names[] = 'remember_token';
                    break;

                default:
                    if ($firstParam && is_string($firstParam)) {
                        $names[] = $firstParam;
                    }
                    break;
            }


            // Internal helper
            $hasModifier = function (DbsyncColumn $col, string $name) {
                $modifiers = collect($col->modifiers ?? []);
                return $modifiers->contains(function ($m) use ($name) {
                    $method = is_array($m) ? ($m['method'] ?? '') : $m;
                    return $method === $name;
                });
            };

            $incrementMethods = ['id', 'increments', 'bigIncrements', 'mediumIncrements', 'smallIncrements', 'tinyIncrements'];
            $integerMethods   = ['integer', 'bigInteger', 'mediumInteger', 'smallInteger', 'tinyInteger', 'unsignedInteger', 'unsignedBigInteger', 'unsignedMediumInteger', 'unsignedSmallInteger', 'unsignedTinyInteger',];

            foreach ($names as $name) {
                $columns[$name] = [
                    'source'         => $column->source,
                    'source_config'  => $column->source_config ?? [],
                    'case_transform' => $column->case_transform,
                    'method'            => $method,
                    'name'              => $name,
                    'is_nullable'       => $hasModifier($column, 'nullable'),
                    'is_unique'         => $hasModifier($column, 'unique'),
                    'is_primary'        => $hasModifier($column, 'primary'),
                    'is_auto_increment' => (
                        in_array($method, $incrementMethods, true) // Autoincrement methods
                        ||
                        (in_array($method, $integerMethods, true) && ($params[1] ?? false) === true) // Integer methods with explicit Autoincrement "->integer('col', true)"
                    ),
                ];
            }
        }

        return $columns;
    }

    protected function resolveSelectColumns(array $columnsMeta): array
    {
        return collect($columnsMeta)
            ->filter(fn($meta) => $meta['source'] === SourceVo::table->value)
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Resolve case transformation rules per column.
     *
     * Returns: ['column_name' => 'upper|lower']
     */
    protected function resolveCaseTransforms(array $columnsMeta): array
    {
        return collect($columnsMeta)
            ->filter(fn($meta) => !empty($meta['case_transform']))
            ->mapWithKeys(fn($meta, $name) => [$name => $meta['case_transform']])
            ->all();
    }

    protected function resolveInsertableColumns(array $columnsMeta): array
    {
        return collect($columnsMeta)
            ->filter(function ($meta) {
                if ($meta['source'] === SourceVo::table->value) {
                    return true;
                }

                return in_array($meta['source_config']['type'] ?? null, ['uuid', 'ulid'], true);
            })
            ->keys()
            ->values()
            ->all();
    }

    protected function resolveVirtualGenerators(array $columnsMeta): array
    {
        return collect($columnsMeta)
            ->filter(fn($meta) => $meta['source'] === SourceVo::virtual->value)
            ->filter(fn($meta) => in_array($meta['source_config']['type'] ?? null, ['uuid', 'ulid'], true))
            ->mapWithKeys(fn($meta, $name) => [$name => $meta['source_config']['type']])
            ->all();
    }
}
