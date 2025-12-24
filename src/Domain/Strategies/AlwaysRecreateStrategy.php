<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Strategies;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thehouseofel\Dbsync\Domain\Contracts\SyncStrategy;
use Thehouseofel\Dbsync\Domain\Data\TableDataCopier;
use Thehouseofel\Dbsync\Domain\Shema\TableSchemaBuilder;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncDatabase;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class AlwaysRecreateStrategy implements SyncStrategy
{
    public function __construct(
        protected TableSchemaBuilder $schemaBuilder,
        protected TableDataCopier    $dataCopier
    )
    {
    }

    public function sync(
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
}
