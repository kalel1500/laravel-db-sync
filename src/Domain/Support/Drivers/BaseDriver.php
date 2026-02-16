<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

use Illuminate\Database\Connection;
use Thehouseofel\Dbsync\Domain\Contracts\SchemaDriver;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

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

    public function truncate(string $table, string $column = 'id'): void
    {
        $this->connection->table($table)->truncate();
    }

    public function insertBulk(string $targetTable, array $rows): void
    {
        $this->connection->table($targetTable)->insert($rows);
    }

    public function insertRowByRow(string $targetTable, array $rows): void
    {
        $this->connection->transaction(function () use ($targetTable, $rows) {
            foreach ($rows as $row) {
                $this->connection->table($targetTable)->insert($row);
            }
        });
    }

    public function insertAuto(DbsyncTable $table, string $targetTable, array $rows): void
    {
        $this->insertBulk($targetTable, $rows);
    }
}
