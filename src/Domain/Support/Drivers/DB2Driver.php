<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

class DB2Driver extends BaseDriver
{
    public function forceDrop(string $table): void
    {
        try {
            // Intentamos el borrado estándar primero
            parent::forceDrop($table);
            return;
        } catch (\Throwable) {
            // Fallback: Si falla por restricciones externas activas, las buscamos en QSYS2
        }

        $schema = $this->currentSchema();
        $tableName = strtoupper($table); // El iSeries guarda los nombres en mayúsculas

        // Consulta adaptada a QSYS2 de IBM iSeries para encontrar tablas dependientes (hijas)
        $constraints = $this->connection->select(
            "SELECT
            RTRIM(CHILD.TABLE_SCHEMA) AS TABSCHEMA,
            RTRIM(CHILD.TABLE_NAME) AS TABNAME,
            RTRIM(CHILD.CONSTRAINT_NAME) AS CONSTNAME
         FROM QSYS2.REFERENTIAL_CONSTRAINTS CHILD
         JOIN QSYS2.REFERENTIAL_CONSTRAINTS PARENT
           ON CHILD.UNIQUE_CONSTRAINT_SCHEMA = PARENT.CONSTRAINT_SCHEMA
          AND CHILD.UNIQUE_CONSTRAINT_NAME = PARENT.CONSTRAINT_NAME
         WHERE PARENT.TABLE_SCHEMA = ? AND PARENT.TABLE_NAME = ?",
            [$schema, $tableName]
        );

        foreach ($constraints as $constraint) {
            $row = (array) $constraint;
            $tabSchema = $this->wrapIdentifier((string) ($row['TABSCHEMA'] ?? ''));
            $tabName = $this->wrapIdentifier((string) ($row['TABNAME'] ?? ''));
            $constraintName = $this->wrapIdentifier((string) ($row['CONSTNAME'] ?? ''));

            if ($tabSchema === '""' || $tabName === '""' || $constraintName === '""') {
                continue;
            }

            // Ejecuta el comando ALTER TABLE bajo la sintaxis de IBM i
            $this->connection->statement(
                "ALTER TABLE {$tabSchema}.{$tabName} DROP FOREIGN KEY {$constraintName}"
            );
        }

        // Reintentamos el borrado de la tabla principal
        parent::forceDrop($table);
    }

    public function truncate(string $table, string $column = 'id'): void
    {
        $wrappedTable  = $this->wrapTable($table);
        $wrappedColumn = $this->wrapColumn($column);
        $next          = ($this->connection->table($table)->max($column) ?? 0) + 1;

        try {
            $this->connection->statement("TRUNCATE TABLE {$wrappedTable} IMMEDIATE");
        } catch (\Throwable) {
            $this->connection->statement("DELETE FROM {$wrappedTable}");
        }

        $this->connection->statement(
            "ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} RESTART WITH {$next}"
        );
    }

    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $wrappedTable  = $this->wrapTable($table);
        $wrappedColumn = $this->wrapColumn($column);
        $next          = ($this->connection->table($table)->max($column) ?? 0) + 1;

        $this->connection->statement(
            "ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} RESTART WITH {$next}"
        );
    }

    protected function currentSchema(): string
    {
        // El paquete guarda el esquema configurado en .env. Intentamos leerlo de ahí primero.
        $schema = strtoupper((string) ($this->connection->getConfig('schema') ?? ''));
        if ($schema !== '') {
            return $schema;
        }

        // Si no está configurado, le preguntamos al iSeries por la biblioteca actual
        $current = $this->connection->selectOne('SELECT CURRENT SCHEMA AS CURRENT_SCHEMA FROM SYSIBM.SYSDUMMY1');
        $row = (array) $current;

        return strtoupper(trim((string) ($row['CURRENT_SCHEMA'] ?? '')));
    }

    protected function wrapIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', trim($identifier)) . '"';
    }

}

