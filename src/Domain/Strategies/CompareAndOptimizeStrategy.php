<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Strategies;

use Illuminate\Support\Facades\Schema;
use Thehouseofel\Dbsync\Domain\Contracts\SyncStrategy;
use Thehouseofel\Dbsync\Domain\Data\TableDataCopier;
use Thehouseofel\Dbsync\Domain\Shema\TableSchemaBuilder;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncDatabase;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class CompareAndOptimizeStrategy implements SyncStrategy
{
    public function __construct(
        protected TableSchemaBuilder $schemaBuilder,
        protected TableDataCopier    $dataCopier,
    )
    {
    }

    public function sync(
        DbsyncConnection $connection,
        DbsyncDatabase   $database,
        DbsyncTable      $table,
    ): void
    {
        // TODO:
        // 1. Read current target schema
        // 2. Normalize expected schema (from dbsync_columns)
        // 3. Compare both
        // 4. Decide strategy:
        //    - truncate
        //    - alter
        //    - drop + recreate
        // 5. Copy data
    }
}
