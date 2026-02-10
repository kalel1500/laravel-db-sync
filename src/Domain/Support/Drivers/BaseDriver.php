<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

use Illuminate\Database\Connection;
use Thehouseofel\Dbsync\Domain\Contracts\SchemaDriver;

abstract class BaseDriver implements SchemaDriver
{
    public function __construct(protected Connection $connection)
    {
    }

    protected function getTableFullName(string $table): string
    {
        return $this->connection->getTablePrefix() . $table;
    }

    public function forceDrop(string $table): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->disableForeignKeyConstraints();
        $schema->dropIfExists($table);
        $schema->enableForeignKeyConstraints();
    }
}
