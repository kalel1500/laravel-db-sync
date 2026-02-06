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
        $tempTable   = $table->target_table . '_tmp';
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

    protected function dropTableIfExists(Builder $builder, string $tableName): void
    {
        $builder->disableForeignKeyConstraints();
        try {
            $builder->dropIfExists($tableName);
        } finally {
            $builder->enableForeignKeyConstraints();
        }
    }
}
