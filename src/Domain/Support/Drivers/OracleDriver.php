<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class OracleDriver extends BaseDriver
{
    protected array $hasTextColumns = [];

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
            $this->connection->statement("DROP TABLE {$this->wrapTable($table)} CASCADE CONSTRAINTS PURGE");
        }
    }

    public function truncate(string $table, string $column = 'id'): void
    {
        $upperTable  = strtoupper($this->getTableFullName($table));
        $upperColumn = strtoupper($column);

        $this->connection->table($table)->truncate();
        try {
            // Intentamos reiniciar la secuencia de identidad propia de Oracle 12c+
            $this->connection->statement("ALTER TABLE {$this->wrapTable($table)} MODIFY ($upperColumn GENERATED AS IDENTITY (START WITH 1))");
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

    public function insertAuto(DbsyncTable $table, string $targetTable, array $rows): void
    {
        if ($table->insert_row_by_row && $this->hasTextLikeColumns($table)) {
            $this->insertRowByRow($targetTable, $rows);
            return;
        }

        $this->insertBulk($targetTable, $rows);
    }

    protected function hasTextLikeColumns(DbsyncTable $table): bool
    {
        $tableKey = $table->id;

        return $this->hasTextColumns[$tableKey] ??= $table->columns->contains(function ($column) {
            return in_array($column->method, ['text', 'mediumText', 'longText']);
        });
    }
}
