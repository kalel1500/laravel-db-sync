<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class SQLiteDriver extends BaseDriver
{
    public function forceDrop(string $table): void
    {
        $schema = $this->connection->getSchemaBuilder();

        // SQLite requiere PRAGMA para ignorar las FKs totalmente
        $this->connection->statement('PRAGMA foreign_keys = OFF');
        $schema->dropIfExists($table);
        $this->connection->statement('PRAGMA foreign_keys = ON');
    }

    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $max = $this->connection->table($table)->max($column);
        $next = ($max ?? 0);

        $this->connection->statement(
            "UPDATE sqlite_sequence SET seq = {$next} WHERE name = ?",
            [$table]
        );
    }
}
