<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Thehouseofel\Dbsync\Domain\Support\SchemaManager connection(string $connection)
 * @method static void forceDrop(string $table)
 * @method static void truncate(array $tables)
 *
 * @see \Thehouseofel\Dbsync\Domain\Support\SchemaManager
 */
class DbsyncSchema extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'thehouseofel.dbsync.schemaManager';
    }
}
