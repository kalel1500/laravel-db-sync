<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class SchemaManager
{
    protected Connection $connection;

    public function __construct()
    {
        $this->connection = DB::connection(config('database.default'));
    }

    public function connection(Connection|string $connection): static
    {
        $this->connection = is_string($connection) ? DB::connection($connection) : $connection;
        return $this;
    }

    /**
     * Borra una tabla ignorando restricciones de integridad según el driver.
     */
    public function forceDrop(string $table): void
    {
        $schema              = $this->connection->getSchemaBuilder();
        $driver              = $this->connection->getDriverName();
        $prefix              = $this->connection->getTablePrefix();
        $tableNameWithPrefix = $prefix . $table;

        if (! $schema->hasTable($table)) {
            return;
        }

        switch ($driver) {
            case 'oci8':
            case 'oracle':
                // Obtener el nombre en mayúsculas (Oracle es case-sensitive en el diccionario)
                $upperTable = strtoupper($tableNameWithPrefix);

                // Comprobar si la tabla existe en user_tables
                $tableExists = $this->connection->selectOne(
                    "SELECT count(*) as total FROM user_tables WHERE table_name = ?",
                    [$upperTable]
                );

                if ($tableExists->total > 0) {
                    // CASCADE CONSTRAINTS elimina las FKs que apuntan a esta tabla
                    $this->connection->statement("DROP TABLE {$upperTable} CASCADE CONSTRAINTS");
                }
                break;

            case 'pgsql':
                // CASCADE elimina FKs, vistas y otros objetos dependientes
                $this->connection->statement("DROP TABLE IF EXISTS {$tableNameWithPrefix} CASCADE");
                break;

            case 'sqlsrv':
                // SQL Server no tiene CASCADE en el DROP. Hay que borrar las FKs manualmente primero.
                $this->dropSqlServerForeignKeys($this->connection, $tableNameWithPrefix);
                $schema->dropIfExists($table);
                break;

            case 'sqlite':
                // SQLite requiere PRAGMA para ignorar las FKs totalmente
                $this->connection->statement('PRAGMA foreign_keys = OFF');
                $schema->dropIfExists($table);
                $this->connection->statement('PRAGMA foreign_keys = ON');
                break;

            default: // mysql | mariadb |
                $schema->disableForeignKeyConstraints();
                $schema->dropIfExists($table);
                $schema->enableForeignKeyConstraints();
                break;
        }
    }

    /**
     * Helper específico para SQL Server (el más complejo en este caso)
     */
    protected function dropSqlServerForeignKeys(Connection $connection, string $tableName): void
    {
        $sql = "SELECT 'ALTER TABLE ' + OBJECT_SCHEMA_NAME(parent_object_id) + '.[' + OBJECT_NAME(parent_object_id) + '] DROP CONSTRAINT [' + name + ']'
            FROM sys.foreign_keys
            WHERE referenced_object_id = OBJECT_ID(?)";

        $constraints = $connection->select($sql, [$tableName]);

        foreach ($constraints as $constraint) {
            // El resultado del select es el comando SQL completo
            $connection->statement(current((array)$constraint));
        }
    }
}
