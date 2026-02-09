<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Sync;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Thehouseofel\Dbsync\Domain\Data\TableDataCopier;
use Thehouseofel\Dbsync\Domain\Shema\TableSchemaBuilder;
use Thehouseofel\Dbsync\Domain\Traits\HasShortNames;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncColumn;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;
use Throwable;

class TableSynchronizer
{
    use HasShortNames;

    public function __construct(
        protected TableSchemaBuilder $schemaBuilder,
        protected TableDataCopier    $dataCopier
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function sync(
        DbsyncConnection $connection,
        DbsyncTable      $table
    ): int
    {
        if ($table->use_temporal_table && $this->hasSelfReferencingForeignKey($table)) {
            throw new \RuntimeException('Table has self-referencing foreign keys, cannot use temporal table strategy.');
        }

        $targetConnection = DB::connection($connection->target_connection);
        $targetShema      = $targetConnection->getSchemaBuilder();
        return $table->use_temporal_table
            ? $this->syncUsingTemporalTable($connection, $table, $targetConnection, $targetShema)
            : $this->syncUsingDrop($connection, $table, $targetConnection, $targetShema);
    }

    protected function syncUsingDrop(
        DbsyncConnection $connection,
        DbsyncTable      $table,
        Connection       $targetConnection,
        Builder          $targetShema,
    ): int
    {
        // 1. Borrado forzoso (usando CASCADE en Oracle/Postgres)
        $this->forceDropTableIfExists($targetConnection, $table->target_table);

        // 2. Creación de la nueva tabla
        $targetShema->create($table->target_table, function (Blueprint $blueprint) use ($table) {
            $this->schemaBuilder->create($blueprint, $table);
        });

        // 3. Reconstrucción CONDICIONAL de FKs externas
        if ($this->driverDestroysForeignKeys($targetConnection)) {
            $this->rebuildDependentForeignKeys($targetShema, $table);
        }

        // 4. Copia de datos
        return $this->dataCopier->copy($connection, $table);
    }

    /**
     * @throws Throwable
     */
    protected function syncUsingTemporalTable(
        DbsyncConnection $connection,
        DbsyncTable      $table,
        Connection       $targetConnection,
        Builder          $targetShema,
    ): int
    {
        // 1. Crear nombre de tabla temporal único
        $tempTable = $this->temporaryTableName($table->target_table);

        // 2. Borrado forzoso de la tabla temporal por si acaso (aunque debería ser única)
        $this->forceDropTableIfExists($targetConnection, $tempTable);

        // 3. Creación de la nueva tabla temporal
        $targetShema->create($tempTable, function (Blueprint $blueprint) use ($table) {
            $this->schemaBuilder->create($blueprint, $table);
        });

        // 4. Reconstrucción CONDICIONAL de FKs externas
        if ($this->driverDestroysForeignKeys($targetConnection)) {
            $this->rebuildDependentForeignKeys($targetShema, $table);
        }

        try {
            // 5. Copiar datos a la temporal
            $rows = $this->dataCopier->copyToTarget(
                $connection,
                $table,
                $tempTable
            );
        } catch (Throwable $e) {
            // Limpieza de la tabla temporal en caso de error
            $this->forceDropTableIfExists($targetConnection, $tempTable);

            throw $e;
        }

        // 6. Swap final (no transaccional por limitaciones DDL cross-engine)
        $this->forceDropTableIfExists($targetConnection, $table->target_table);
        $targetShema->rename($tempTable, $table->target_table);

        // 7. Retornar número de filas copiadas
        return $rows;
    }

    protected function temporaryTableName(string $targetTable): string
    {
        return substr($targetTable, 0, 20) . '_t' . substr(md5((string)now()->timestamp), 0, 4);
    }

    protected function hasSelfReferencingForeignKey(DbsyncTable $table): bool
    {
        $targetTable = $table->target_table;

        foreach ($table->columns as $column) {
            if ($column->method !== 'foreignId') {
                continue;
            }

            if ($column->self_referencing) {
                return true;
            }

            $parameters = $column->parameters ?? [];
            $modifiers  = $column->modifiers ?? [];

            // Nombre de la columna FK (foreignId('comment_id'))
            $columnName = $parameters[0] ?? null;

            // Si no se especifica no hacer nada y dejar que falle luego al crear la tabla
            if (! $columnName) {
                continue;
            }

            foreach ($modifiers as $modifier) {
                // Normalizamos modifier a array
                if (is_string($modifier)) {
                    $modifier = ['method' => $modifier];
                }

                if ($modifier['method'] !== 'constrained') {
                    continue;
                }

                // Si constrained tiene parámetros, NO es implícita → no es problema
                $constrainedParameters = $modifier['parameters'] ?? [];

                if (! empty($constrainedParameters)) {
                    continue;
                }

                /**
                 * constrained() sin parámetros:
                 * Laravel infiere la tabla desde el nombre de la columna
                 * Ej: comment_id -> comments
                 */
                $base = str($columnName)->replaceLast('_id', '');
                if (
                    $targetTable === $base->plural()->toString() ||
                    $targetTable === $base->singular()->toString()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Elimina una tabla de forma segura rompiendo restricciones de integridad.
     */
    protected function forceDropTableIfExists(Connection $connection, string $tableName): void
    {
        $schema = $connection->getSchemaBuilder();
        $driver = $connection->getDriverName();

        // Obtenemos el nombre con el prefijo configurado
        $prefix              = $connection->getConfig('prefix') ?? ''; // $connection->getTablePrefix()
        $tableNameWithPrefix = $prefix . $tableName;

        switch ($driver) {
            case 'oci8':
            case 'oracle':
                // Obtener el nombre en mayúsculas (Oracle es case-sensitive en el diccionario)
                $upperTable = strtoupper($tableNameWithPrefix);

                // Comprobar si la tabla existe en user_tables
                $tableExists = $connection->selectOne(
                    "SELECT count(*) as total FROM user_tables WHERE table_name = ?",
                    [$upperTable]
                );

                if ($tableExists->total > 0) {
                    // CASCADE CONSTRAINTS elimina las FKs que apuntan a esta tabla
                    $connection->statement("DROP TABLE {$upperTable} CASCADE CONSTRAINTS");
                }
                break;

            case 'pgsql':
                // CASCADE elimina FKs, vistas y otros objetos dependientes
                $connection->statement("DROP TABLE IF EXISTS {$tableNameWithPrefix} CASCADE");
                break;

            case 'sqlsrv':
                // SQL Server no tiene CASCADE en el DROP. Hay que borrar las FKs manualmente primero.
                $this->dropSqlServerForeignKeys($connection, $tableNameWithPrefix);
                $schema->dropIfExists($tableName);
                break;

            case 'sqlite':
                // SQLite requiere PRAGMA para ignorar las FKs totalmente
                $connection->statement('PRAGMA foreign_keys = OFF');
                $schema->dropIfExists($tableName);
                $connection->statement('PRAGMA foreign_keys = ON');
                break;

            default: // mysql | mariadb |
                $schema->disableForeignKeyConstraints();
                $schema->dropIfExists($tableName);
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

    /**
     * Determina si el driver de destino elimina físicamente las claves foráneas al eliminar la tabla.
     * Esto es importante para decidir si necesitamos reconstruir las FKs dependientes.
     */
    protected function driverDestroysForeignKeys(Connection $connection): bool
    {
        $driver = $connection->getDriverName();

        // Oracle, Postgres y SQL Server eliminan o requieren eliminar las constraints físicamente
        return in_array($driver, ['oracle', 'oci8', 'pgsql', 'sqlsrv']);
    }

    /**
     * Busca y recrea las claves foráneas de otras tablas que apuntan a la tabla recién sincronizada.
     */
    protected function rebuildDependentForeignKeys(Builder $targetShema, DbsyncTable $syncedTable): void
    {
        $tableName = $syncedTable->target_table;

        // 1. Buscamos columnas de otras tablas que apunten a esta
        $dependentColumns = DbsyncColumn::whereHas('tables', function ($query) use ($syncedTable) {
            $query->where('dbsync_tables.id', '!=', $syncedTable->id);
        })->where(function ($query) {
            $query->where('method', 'foreignId')
                ->orWhere('modifiers', 'LIKE', '%constrained%');
        })->get();

        foreach ($dependentColumns as $column) {
            if ($this->guessReferencedTable($column) === $tableName) {
                foreach ($column->tables as $tableToFix) {
                    // Solo si la tabla ya existe en el destino
                    if (! $targetShema->hasTable($tableToFix->target_table)) continue;

                    $targetShema->table($tableToFix->target_table, function (Blueprint $blueprint) use ($column, $tableToFix) {
                        $colName = $column->parameters[0];

                        // Extraemos el modificador 'constrained' original
                        $constrainedModifier = collect($column->modifiers)->firstWhere('method', 'constrained');
                        $originalParams      = is_array($constrainedModifier) ? ($constrainedModifier['parameters'] ?? []) : [];

                        // Aplicamos el nombre corto (o el del usuario)
                        $finalParams = $this->applyShortName($tableToFix->target_table, $colName, 'constrained', $originalParams);

                        // Re-creamos la clave foránea
                        // $finalParams[0] es la tabla, [1] la columna referenciada, [2] el nombre del índice
                        $blueprint->foreign($colName, $finalParams[2])
                            ->references($finalParams[1] ?? 'id')
                            ->on($finalParams[0] ?? $this->guessReferencedTable($column))
                            ->cascadeOnDelete();
                    });
                }
            }
        }
    }

    /**
     * Lógica para obtener el nombre de la tabla referenciada (Paso 4 y 5 de tu propuesta)
     */
    protected function guessReferencedTable(DbsyncColumn $column): ?string
    {
        $modifiers = $column->modifiers ?? [];

        foreach ($modifiers as $modifier) {
            if (is_array($modifier) && $modifier['method'] === 'constrained') {
                // Si tiene parámetro en constrained: constrained('users')
                if (! empty($modifier['parameters'][0])) {
                    return $modifier['parameters'][0];
                }
            }
        }

        // Si no hay parámetro en constrained, inferimos por el nombre de la columna: user_id -> users
        $colName = $column->parameters[0] ?? '';
        return Str::plural(Str::before($colName, '_id'));
    }
}
