<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class SqlServerDriver extends BaseDriver
{
    public function forceDrop(string $table): void
    {
        $schema       = $this->connection->getSchemaBuilder();
        $tableNameRaw = $this->getDictionaryTableName($table);

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

    /*public function truncate(string $table, string $column = 'id'): void
    {
        $tableNameRaw = $this->getTableFullName($table);

        // SQL Server: TRUNCATE no funciona si la tabla es referenciada por una FK
        // aunque la otra tabla esté vacía. Hay que usar DELETE si hay FKs.
        try {
            $this->connection->statement("TRUNCATE TABLE [{$tableNameRaw}]");
        } catch (\Exception $e) {
            $this->connection->statement("DELETE FROM [{$tableNameRaw}]");
            // Reseteamos el SEED del IDENTITY manualmente
            $this->connection->statement("DBCC CHECKIDENT ('[{$tableNameRaw}]', RESEED, 0)");
        }
    }*/
}
