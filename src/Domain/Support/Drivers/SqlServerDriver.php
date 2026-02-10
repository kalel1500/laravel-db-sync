<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class SqlServerDriver extends BaseDriver
{
    public function forceDrop(string $table): void
    {
        $schema       = $this->connection->getSchemaBuilder();
        $tableNameRaw = $this->getTableFullName($table);

        // SQL Server no tiene CASCADE en el DROP. Hay que borrar las FKs manualmente primero.

        $sql = "SELECT 'ALTER TABLE ' + OBJECT_SCHEMA_NAME(parent_object_id) + '.[' + OBJECT_NAME(parent_object_id) + '] DROP CONSTRAINT [' + name + ']'
            FROM sys.foreign_keys
            WHERE referenced_object_id = OBJECT_ID(?)";
        $constraints = $this->connection->select($sql, [$tableNameRaw]);
        foreach ($constraints as $constraint) {
            // El resultado del select es el comando SQL completo
            $this->connection->statement(current((array)$constraint));
        }

        $schema->dropIfExists($table);
    }
}
