<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class MySqlDriver extends BaseDriver
{
    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $wrappedTable = $this->wrapTable($table);

        $max = $this->connection->table($table)->max($column);
        $next = ($max ?? 0) + 1;

        $this->connection->statement(
            "ALTER TABLE {$wrappedTable} AUTO_INCREMENT = {$next}"
        );
    }

}
