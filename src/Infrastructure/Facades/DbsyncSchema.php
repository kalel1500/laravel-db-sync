<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Thehouseofel\Dbsync\Domain\Support\SchemaConnection connection(\Illuminate\Database\Connection|string|null $connection)
 * @method static void forceDrop(string $table)
 * @method static void truncate(array $tables)
 *
 * @see \Thehouseofel\Dbsync\Domain\Support\SchemaConnection
 */
class DbsyncSchema extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'thehouseofel.dbsync.schemaConnection';
    }
}
