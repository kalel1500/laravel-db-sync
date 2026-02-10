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

    public function truncate(string $table, string $column = 'id'): void
    {
        $upperTable  = strtoupper($this->getTableFullName($table));
        $upperColumn = strtoupper($column);

        $this->connection->table($table)->truncate();
        try {
            // Intentamos reiniciar la secuencia de identidad propia de Oracle 12c+
            $this->connection->statement("ALTER TABLE $upperTable MODIFY ($upperColumn GENERATED AS IDENTITY (START WITH 1))");
        } catch (\Throwable $e) {
            // Si falla el comando anterior (porque no es Identity o la versión es vieja),
            // buscamos si hay una secuencia asociada manualmente.

            // Buscamos la secuencia más probable (Laravel suele usar NOMBRE_TABLA_SEQ)
            $sequenceName = $upperTable . "_SEQ";

            // Verificamos si la secuencia existe
            $exists = $this->connection->selectOne("SELECT sequence_name FROM user_sequences WHERE sequence_name = ?", [$sequenceName]);

            if ($exists) {
                // En Oracle no hay "RESTART WITH 1" para secuencias de forma directa y fácil,
                // lo más limpio es borrarla y volverla a crear.
                $this->connection->statement("DROP SEQUENCE {$sequenceName}");
                $this->connection->statement("CREATE SEQUENCE {$sequenceName} START WITH 1 INCREMENT BY 1 NOCACHE");
            }
        }
    }
}
