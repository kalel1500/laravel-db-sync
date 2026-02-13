<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Thehouseofel\Dbsync\Domain\Data\TableDataCopier;
use Thehouseofel\Dbsync\Domain\Shema\TableSchemaBuilder;
use Thehouseofel\Dbsync\Domain\Traits\HasShortNames;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncColumn;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;
use Throwable;

class TableSynchronizer
{
    use HasShortNames;

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
        if ($table->use_temporal_table && $this->schemaBuilder->hasSelfReferencingForeignKey($table)) {
            throw new \RuntimeException('Table has self-referencing foreign keys, cannot use temporal table strategy.');
        }

        $targetConnection = DB::connection($connection->target_connection);
        $targetShema      = $targetConnection->getSchemaBuilder();
        return $table->use_temporal_table
            ? $this->syncUsingTemporalTable($connection, $table, $targetConnection, $targetShema)
            : $this->syncUsingDrop($connection, $table, $targetConnection, $targetShema);
    }

    protected function syncUsingDrop(
        DbsyncConnection $connection,
        DbsyncTable      $table,
        Connection       $targetConnection,
        Builder          $targetShema,
    ): int
    {
        // 1. Borrado forzoso (usando CASCADE en Oracle/Postgres)
        $this->schemaBuilder->forceDropTableIfExists($targetConnection, $table->target_table);

        // 2. Creación de la nueva tabla
        $targetShema->create($table->target_table, function (Blueprint $blueprint) use ($table) {
            $this->schemaBuilder->create($blueprint, $table);
        });

        // 3. Copia de datos
        $rows = $this->dataCopier->copy($connection, $table);

        // 4. Reconstrucción CONDICIONAL de FKs externas
        if ($this->schemaBuilder->driverDestroysForeignKeys($targetConnection)) {
            $this->schemaBuilder->rebuildDependentForeignKeys($targetShema, $table);
        }

        return $rows;
    }

    /**
     * @throws Throwable
     */
    protected function syncUsingTemporalTable(
        DbsyncConnection $connection,
        DbsyncTable      $table,
        Connection       $targetConnection,
        Builder          $targetShema,
    ): int
    {
        // 1. Crear nombre de tabla temporal único
        $tempTable = $this->temporaryTableName($table->target_table);

        // 2. Borrado forzoso de la tabla temporal por si acaso (aunque debería ser única)
        $this->schemaBuilder->forceDropTableIfExists($targetConnection, $tempTable);

        // 3. Creación de la nueva tabla temporal
        $targetShema->create($tempTable, function (Blueprint $blueprint) use ($table) {
            $this->schemaBuilder->create($blueprint, $table);
        });

        try {
            // 4. Copiar datos a la temporal
            $rows = $this->dataCopier->copyToTarget(
                $connection,
                $table,
                $tempTable
            );
        } catch (Throwable $e) {
            // Limpieza de la tabla temporal en caso de error
            $this->schemaBuilder->forceDropTableIfExists($targetConnection, $tempTable);

            throw $e;
        }

        // 5. Swap final (no transaccional por limitaciones DDL cross-engine)
        $this->schemaBuilder->forceDropTableIfExists($targetConnection, $table->target_table);
        $targetShema->rename($tempTable, $table->target_table);

        // 6. Reconstrucción CONDICIONAL de FKs externas
        if ($this->schemaBuilder->driverDestroysForeignKeys($targetConnection)) {
            $this->schemaBuilder->rebuildDependentForeignKeys($targetShema, $table);
        }

        // 7. Retornar número de filas copiadas
        return $rows;
    }

    protected function temporaryTableName(string $targetTable): string
    {
        return substr($targetTable, 0, 20) . '_t' . substr(md5((string)now()->timestamp), 0, 4);
    }

}
