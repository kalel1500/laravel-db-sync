<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Facades;

use Illuminate\Support\Facades\Facade;
use Thehouseofel\Dbsync\Domain\Contracts\SchemaFactory;

/**
 * @method static \Thehouseofel\Dbsync\Domain\Support\SchemaConnection connection(\Illuminate\Database\Connection|string|null $connection)
 * @method static bool isOracle()
 * @method static void forceDrop(string $table)
 * @method static void truncate(array $tables)
 * @method static void syncIdentity(string $table, string $column = 'id')
 * @method static void disableBuffer()
 *
 * @see \Thehouseofel\Dbsync\Domain\Support\SchemaManager
 */
class DbsyncSchema extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SchemaFactory::class;
    }
}
