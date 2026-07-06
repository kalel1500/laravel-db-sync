<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class DB2Driver extends BaseDriver
{
    public function truncate(string $table, string $column = 'id'): void
    {
        $wrappedTable  = $this->wrapTable($table);
        $wrappedColumn = $this->wrapColumn($column);
        $next          = ($this->connection->table($table)->max($column) ?? 0) + 1;

        try {
            $this->connection->statement("TRUNCATE TABLE {$wrappedTable} IMMEDIATE");
        } catch (\Throwable) {
            $this->connection->statement("DELETE FROM {$wrappedTable}");
        }

        $this->connection->statement(
            "ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} RESTART WITH {$next}"
        );
    }

    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $wrappedTable  = $this->wrapTable($table);
        $wrappedColumn = $this->wrapColumn($column);
        $next          = ($this->connection->table($table)->max($column) ?? 0) + 1;

        $this->connection->statement(
            "ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} RESTART WITH {$next}"
        );
    }
}

