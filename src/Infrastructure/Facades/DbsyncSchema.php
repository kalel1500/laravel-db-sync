<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Infrastructure\Facades;

use Illuminate\Support\Facades\Facade;
use Thehouseofel\Dbsync\Domain\Contracts\SchemaFactory;

/**
 * @method static \Thehouseofel\Dbsync\Domain\Support\SchemaManager connection(\Illuminate\Database\Connection|string|null $connection)
 * @method static void forceDrop(string $table)
 * @method static void truncate(array $tables)
 * @method static void syncIdentity(string $table, string $column = 'id')
 * @method static void insert(\Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable $table, string $targetTable, array $rows)
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
