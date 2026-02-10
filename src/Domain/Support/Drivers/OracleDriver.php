<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class OracleDriver extends BaseDriver
{
    public function forceDrop(string $table): void
    {
        // Obtener el nombre en mayúsculas (Oracle es case-sensitive en el diccionario)
        $upperTable = strtoupper($this->getTableFullName($table));

        // Comprobar si la tabla existe en user_tables
        $tableExists = $this->connection->selectOne(
            "SELECT count(*) as total FROM user_tables WHERE table_name = ?",
            [$upperTable]
        );

        if ($tableExists->total > 0) {
            // CASCADE CONSTRAINTS elimina las FKs que apuntan a esta tabla
            $this->connection->statement("DROP TABLE {$upperTable} CASCADE CONSTRAINTS");
        }
    }
}
