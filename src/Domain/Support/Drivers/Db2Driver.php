<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class Db2Driver extends BaseDriver
{
    public function forceDrop(string $table): void
    {
        // DB2 drops the foreign keys that reference the table along with the table.
        $this->connection->getSchemaBuilder()->drop($table);
    }

    public function truncate(string $table, string $column = 'id'): void
    {
        $this->connection->statement("TRUNCATE TABLE {$this->wrapTable($table)} RESTART IDENTITY IMMEDIATE");
    }

    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $max  = $this->connection->table($table)->max($column);
        $next = ($max ?? 0) + 1;

        $this->connection->statement(
            "ALTER TABLE {$this->wrapTable($table)} " .
            "ALTER COLUMN {$this->wrapColumn($column)} RESTART WITH {$next}"
        );
    }
}
