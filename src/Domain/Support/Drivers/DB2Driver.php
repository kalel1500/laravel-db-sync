<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

use Composer\InstalledVersions;
use Throwable;

class DB2Driver extends BaseDriver
{
    protected function validateVersion(): void
    {
        // Validamos que el paquete drop-in de BWICompanies esté presente
        if (! InstalledVersions::isInstalled('bwicompanies/db2-driver')) {
            throw new \RuntimeException(
                "The package 'bwicompanies/db2-driver' is not installed. Please install it to use the DB2 driver."
            );
        }

        // Validamos que la conexión actual sea la adecuada del driver 'db2'
        if ($this->connection->getDriverName() !== 'db2') {
            throw new \RuntimeException(
                "The connection driver is not 'db2'. Please check your database.php configuration."
            );
        }
    }

    protected function getDictionaryTableName(string $table): string
    {
        // IBM iSeries almacena físicamente los nombres de tablas en mayúsculas
        return strtoupper(parent::getDictionaryTableName($table));
    }

    public function forceDrop(string $table): void
    {
        try {
            // 1. Intentamos el borrado nativo de Laravel usando el BaseDriver
            parent::forceDrop($table);
            return;
        } catch (Throwable) {
            // 2. Fallback: IBM i Series bloquea DROP si hay tablas hijas con FKs apuntando aquí.
            // Las buscamos manualmente en las vistas de sistema de QSYS2.
        }

        $schema = $this->currentSchema();
        $tableName = $this->getDictionaryTableName($table);

        // Catálogo de restricciones nativo de IBM i (QSYS2.REFERENTIAL_CONSTRAINTS)
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

            // Eliminamos físicamente la FK de la tabla hija para liberar el bloqueo
            $this->connection->statement(
                "ALTER TABLE {$tabSchema}.{$tabName} DROP FOREIGN KEY {$constraintName}"
            );
        }

        // 3. Reintentamos el borrado estructural completo
        parent::forceDrop($table);
    }

    public function truncate(string $table, string $column = 'id'): void
    {
        $wrappedTable = $this->wrapTable($table);
        $wrappedColumn = $this->wrapColumn($column);

        try {
            // En DB2 iSeries el estándar requiere "IMMEDIATE" para liberar espacio en el diario
            $this->connection->statement("TRUNCATE TABLE {$wrappedTable} IMMEDIATE");
        } catch (Throwable) {
            // Fallback si la tabla no está bajo diario (journal) activo
            $this->connection->table($table)->delete();
        }

        // Como es un TRUNCATE, reiniciamos el autoincremental directamente a 1
        $this->connection->statement(
            "ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} RESTART WITH 1"
        );
    }

    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $wrappedTable = $this->wrapTable($table);
        $wrappedColumn = $this->wrapColumn($column);

        // Aquí sí calculamos el máximo actual porque la tabla conserva registros
        $next = ($this->connection->table($table)->max($column) ?? 0) + 1;

        $this->connection->statement(
            "ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} RESTART WITH {$next}"
        );
    }

    protected function currentSchema(): string
    {
        // Intentamos leer el esquema definido en el .env de Laravel primero
        $schema = strtoupper((string) ($this->connection->getConfig('schema') ?? ''));
        if ($schema !== '') {
            return $schema;
        }

        // Fallback: Si no está definido, le preguntamos directamente al iSeries
        $current = $this->connection->selectOne(
            'SELECT CURRENT SCHEMA AS CURRENT_SCHEMA FROM SYSIBM.SYSDUMMY1'
        );
        $row = (array) $current;

        return strtoupper(trim((string) ($row['CURRENT_SCHEMA'] ?? '')));
    }

    protected function wrapIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', trim($identifier)) . '"';
    }
}

