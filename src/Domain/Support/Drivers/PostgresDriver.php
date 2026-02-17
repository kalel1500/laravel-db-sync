<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class PostgresDriver extends BaseDriver
{
    public function forceDrop(string $table): void
    {
        $tableNameRaw = $this->wrapTable($table);

        // CASCADE elimina FKs, vistas y otros objetos dependientes
        $this->connection->statement("DROP TABLE IF EXISTS {$tableNameRaw} CASCADE");
    }

    /*public function truncate(string $table, string $column = 'id'): void
    {
        $tableNameRaw = $this->getTableFullName($table);
        $this->connection->statement("TRUNCATE TABLE {$tableNameRaw} RESTART IDENTITY CASCADE");
    }*/
}
