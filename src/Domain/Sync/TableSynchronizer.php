<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Thehouseofel\Dbsync\Domain\Data\TableDataCopier;
use Thehouseofel\Dbsync\Domain\Shema\TableSchemaBuilder;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;
use Throwable;

class TableSynchronizer
{
    public function __construct(
        protected TableSchemaBuilder $schemaBuilder,
        protected TableDataCopier    $dataCopier
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function sync(
        DbsyncConnection $connection,
        DbsyncTable      $table
    ): int
    {
        if ($table->use_temporal_table && $this->hasSelfReferencingForeignKey($table)) {
            throw new \RuntimeException('Table has self-referencing foreign keys, cannot use temporal table strategy.');
        }
        return $table->use_temporal_table
            ? $this->syncUsingTemporalTable($connection, $table)
            : $this->syncUsingDrop($connection, $table);
    }

    protected function syncUsingDrop(
        DbsyncConnection $connection,
        DbsyncTable      $table
    ): int
    {
        $this->dropTableIfExists(Schema::connection($connection->target_connection), $table->target_table);

        Schema::connection($connection->target_connection)
            ->create($table->target_table, function (Blueprint $blueprint) use ($table) {
                $this->schemaBuilder->create($blueprint, $table);
            });

        return $this->dataCopier->copy($connection, $table);
    }

    /**
     * @throws Throwable
     */
    protected function syncUsingTemporalTable(
        DbsyncConnection $connection,
        DbsyncTable      $table
    ): int
    {
        $tempTable   = $this->temporaryTableName($table->target_table);
        $targetShema = Schema::connection($connection->target_connection);

        // Limpieza por si quedó algo colgado
        $this->dropTableIfExists($targetShema, $tempTable);

        // Crear tabla temporal
        $targetShema
            ->create($tempTable, function (Blueprint $blueprint) use ($table) {
                $this->schemaBuilder->create($blueprint, $table);
            });

        try {
            // Copiar datos a la temporal
            $rows = $this->dataCopier->copyToTarget(
                $connection,
                $table,
                $tempTable
            );
        } catch (Throwable $e) {
            // Limpieza de la tabla temporal en caso de error
            $this->dropTableIfExists($targetShema, $tempTable);

            throw $e;
        }

        // Swap final (no transaccional por limitaciones DDL cross-engine)
        $this->dropTableIfExists($targetShema, $table->target_table);
        $targetShema->rename($tempTable, $table->target_table);

        return $rows;
    }

    protected function temporaryTableName(string $targetTable): string
    {
        return substr($targetTable, 0, 20) . '_t' . substr(md5((string)now()->timestamp), 0, 4);
    }

    protected function dropTableIfExists(Builder $builder, string $tableName): void
    {
        $builder->disableForeignKeyConstraints();
        try {
            $builder->dropIfExists($tableName);
        } finally {
            $builder->enableForeignKeyConstraints();
        }
    }

    protected function hasSelfReferencingForeignKey(DbsyncTable $table): bool
    {
        $targetTable = $table->target_table;

        foreach ($table->columns as $column) {
            if ($column->method !== 'foreignId') {
                continue;
            }

            if ($column->self_referencing) {
                return true;
            }

            $parameters = $column->parameters ?? [];
            $modifiers  = $column->modifiers ?? [];

            // Nombre de la columna FK (foreignId('comment_id'))
            $columnName = $parameters[0] ?? null;

            // Si no se especifica no hacer nada y dejar que falle luego al crear la tabla
            if (! $columnName) {
                continue;
            }

            foreach ($modifiers as $modifier) {
                // Normalizamos modifier a array
                if (is_string($modifier)) {
                    $modifier = ['method' => $modifier];
                }

                if ($modifier['method'] !== 'constrained') {
                    continue;
                }

                // Si constrained tiene parámetros, NO es implícita → no es problema
                $constrainedParameters = $modifier['parameters'] ?? [];

                if (! empty($constrainedParameters)) {
                    continue;
                }

                /**
                 * constrained() sin parámetros:
                 * Laravel infiere la tabla desde el nombre de la columna
                 * Ej: comment_id -> comments
                 */
                $base = str($columnName)->replaceLast('_id', '');
                if (
                    $targetTable === $base->plural()->toString() ||
                    $targetTable === $base->singular()->toString()
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
