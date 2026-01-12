<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Thehouseofel\Dbsync\Domain\Data\TableDataCopier;
use Thehouseofel\Dbsync\Domain\Shema\TableSchemaBuilder;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncDatabase;
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
        DbsyncDatabase   $database,
        DbsyncTable      $table
    ): int
    {
        return $table->use_temporal_table
            ? $this->syncUsingTemporalTable($connection, $database, $table)
            : $this->syncUsingDrop($connection, $database, $table);
    }

    protected function syncUsingDrop(
        DbsyncConnection $connection,
        DbsyncDatabase   $database,
        DbsyncTable      $table
    ): int
    {
        Schema::connection($connection->target_connection)
            ->dropIfExists($table->target_table);

        Schema::connection($connection->target_connection)
            ->create($table->target_table, function (Blueprint $blueprint) use ($table) {
                $this->schemaBuilder->create($blueprint, $table);
            });

        return $this->dataCopier->copy($connection, $database, $table);
    }

    /**
     * @throws Throwable
     */
    protected function syncUsingTemporalTable(
        DbsyncConnection $connection,
        DbsyncDatabase   $database,
        DbsyncTable      $table
    ): int
    {
        $tempTable = $this->temporaryTableName($table->target_table);

        // Limpieza por si quedó algo colgado
        Schema::connection($connection->target_connection)
            ->dropIfExists($tempTable);

        // Crear tabla temporal
        Schema::connection($connection->target_connection)
            ->create($tempTable, function (Blueprint $blueprint) use ($table) {
                $this->schemaBuilder->create($blueprint, $table);
            });

        // Copiar datos a la temporal
        $rows = $this->dataCopier->copyToTarget(
            $connection,
            $database,
            $table,
            $tempTable
        );

        /**
         * Swap atómico
         *
         * IMPORTANTE: La transacción NO deshace el drop/rename; pero agrupa operaciones críticas, documenta atomicidad en motores que lo soportan, reduce estados intermedios y limita el scope del riesgo
         */
        DB::connection($connection->target_connection)
            ->transaction(function () use ($table, $tempTable) {
                Schema::dropIfExists($table->target_table);
                Schema::rename($tempTable, $table->target_table);
            });

        return $rows;
    }

    protected function temporaryTableName(string $targetTable): string
    {
        return $targetTable . '_tmp_' . uniqid();
    }
}
