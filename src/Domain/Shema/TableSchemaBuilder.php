<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Shema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;
use Thehouseofel\Dbsync\Domain\Traits\HasShortNames;
use Thehouseofel\Dbsync\Infrastructure\Facades\DbsyncSchema;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncColumn;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class TableSchemaBuilder
{
    use HasShortNames;

    protected const METHODS_WITHOUT_NAME_PARAMETER = ['id', 'timestamps', 'softDeletes', 'rememberToken'];

    public function create(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->columns as $column) {
            $this->addColumn($blueprint, $column);
        }

        $this->addPrimaryKey($blueprint, $table);
        $this->addUniqueKeys($blueprint, $table);
        $this->addIndexes($blueprint, $table);
    }

    protected function addColumn(Blueprint $blueprint, DbsyncColumn $column): void
    {
        $params = $column->parameters ?? [];
        $method = $column->method;
        $tableName = $blueprint->getTable();

        if (! in_array($method, self::METHODS_WITHOUT_NAME_PARAMETER) && (empty($params) || ! is_string($params[0]))) {
            throw new \InvalidArgumentException('Column definition requires the column name as first parameter.');
        }

        if (in_array($method, ['foreign', 'index', 'unique', 'primary'])) {
            throw new \InvalidArgumentException(
                "The method '{$method}' is not allowed as a column definition. " .
                "To define constraints, use modifiers (e.g., ->unique()) for single columns " .
                "or the 'dbsync_tables' fields for composite keys."
            );
        }

        // 1. Crear la definición base (ej: $table->string('email'))
        $definition = $blueprint->{$method}(...$params);

        // 2. Aplicar modificadores
        foreach ($column->modifiers ?? [] as $modifier) {
            $mMethod = is_array($modifier) ? $modifier['method'] : $modifier;
            $mParams = is_array($modifier) ? ($modifier['parameters'] ?? []) : [];

            if (in_array($mMethod, ['index', 'unique', 'primary', 'constrained'])) {
                $mParams = $this->applyShortName($tableName, $params[0], $mMethod, $mParams);
            }

            $definition->{$mMethod}(...$mParams);
        }
    }

    protected function addPrimaryKey(Blueprint $blueprint, DbsyncTable $table): void
    {
        if ($table->primary_key) {
            $name = $this->generateShortName($blueprint->getTable(), implode('_', $table->primary_key), 'pk');
            $blueprint->primary($table->primary_key, $name);
        }
    }

    protected function addUniqueKeys(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->unique_keys ?? [] as $columns) {
            if (! is_array($columns)) {
                throw new \InvalidArgumentException(
                    "Table '{$table->target_table}' has an invalid unique_keys format. " .
                    "Each entry must be an array of columns (e.g., [['email'], ['field1', 'field2']])."
                );
            }

            $name = $this->generateShortName($blueprint->getTable(), implode('_', $columns), 'unq');
            $blueprint->unique($columns, $name);
        }
    }

    protected function addIndexes(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->indexes ?? [] as $columns) {
            if (! is_array($columns)) {
                throw new \InvalidArgumentException(
                    "Table '{$table->target_table}' has an invalid indexes format. " .
                    "Each entry must be an array of columns (e.g., [['category_id'], ['active', 'created_at']])."
                );
            }

            $name = $this->generateShortName($blueprint->getTable(), implode('_', $columns), 'idx');
            $blueprint->index($columns, $name);
        }
    }


    // Limpieza

    /**
     * Elimina una tabla de forma segura rompiendo restricciones de integridad.
     */
    public function forceDropTableIfExists(Connection $connection, string $tableName): void
    {
        DbsyncSchema::connection($connection)->forceDrop($tableName);
    }


    // Análisis

    /**
     * Determina si la tabla tiene claves foráneas que apuntan a sí misma.
     * Esto es crucial para decidir si podemos usar la estrategia de tabla temporal o no.
     */
    public function hasSelfReferencingForeignKey(DbsyncTable $table): bool
    {
        $targetTable = $table->target_table;

        foreach ($table->columns as $column) {
            if ($column->method !== 'foreignId') {
                continue;
            }

            // 1. Prioridad absoluta: El flag explícito de la base de datos
            if ($column->self_referencing) {
                return true;
            }

            $columnName = $column->parameters[0] ?? null;
            if (! $columnName) {
                continue;
            }

            // Buscamos el modificador 'constrained'
            $constrained = collect($column->modifiers)->first(function ($modifier) {
                $method = is_string($modifier) ? $modifier : ($modifier['method'] ?? '');
                return $method === 'constrained';
            });

            if (! $constrained) {
                continue;
            }

            // 2. Prioridad media: Si el usuario especificó la tabla en ->constrained('tasks')
            $constrainedParams = is_array($constrained) ? ($constrained['parameters'] ?? []) : [];
            if (! empty($constrainedParams) && isset($constrainedParams[0])) {
                if ($constrainedParams[0] === $targetTable) {
                    return true;
                }
                // Si especificó una tabla y NO es la actual, ya sabemos que no es autorreferencial
                continue;
            }

            // 3. Prioridad baja (Laravel implícito): Adivinar por nombre de columna
            // Ej: 'parent_id' -> 'parent' -> 'parents'
            $base = str($columnName)->replaceLast('_id', '');
            if (
                $targetTable === $base->plural()->toString() ||
                $targetTable === $base->singular()->toString()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina si el driver de destino elimina físicamente las claves foráneas al eliminar la tabla.
     * Esto es importante para decidir si necesitamos reconstruir las FKs dependientes.
     */
    public function driverDestroysForeignKeys(Connection $connection): bool
    {
        $driver = $connection->getDriverName();

        // Oracle, Postgres y SQL Server eliminan o requieren eliminar las constraints físicamente
        return in_array($driver, ['oracle', 'oci8', 'pgsql', 'sqlsrv']);
    }


    // Reparación

    /**
     * Busca y recrea las claves foráneas de otras tablas que apuntan a la tabla recién sincronizada.
     */
    public function rebuildDependentForeignKeys(Builder $targetShema, DbsyncTable $syncedTable): void
    {
        $tableName = $syncedTable->target_table;

        // Buscar columnas de otras tablas que apunten a esta tabla
        $potentialColumns = DbsyncColumn::with('tables')
            ->whereHas('tables', function ($query) use ($syncedTable) {
                $query->where('dbsync_tables.id', '!=', $syncedTable->id);
            })
            ->where(function ($query) {
                $query->where('method', 'foreignId')
                    ->orWhere('modifiers', 'LIKE', '%constrained%');
            })
            ->get();

        foreach ($potentialColumns as $column) {
            // Iteramos las tablas que usan esta columna
            foreach ($column->tables as $tableToFix) {

                // Saltamos la tabla que acabamos de crear, ya que tiene las FKs bien creadas (en teoria ya no vienen en la query)
                if ($tableToFix->id === $syncedTable->id) continue;

                $referencedTable = $this->guessReferencedTable($column);

                // Validar que la FK pertenece a la tabla que acabamos de sincronizar (y no a otra tabla que tenga una columna con el mismo nombre)
                if ($referencedTable !== $tableName) continue;

                // Crear FKs SOLO si existe la tabla referenciada
                if (! $targetShema->hasTable($tableToFix->target_table)) continue;

                $targetShema->table($tableToFix->target_table, function (Blueprint $blueprint) use ($column, $tableToFix, $referencedTable) {
                    $colName = $column->parameters[0];
                    $modifiers = collect($column->modifiers);

                    // 1. Localizar la posición del 'constrained'
                    $constrainedIndex = $modifiers->search(fn($m) => (is_array($m) ? $m['method'] : $m) === 'constrained');

                    // 2. Extraer el modificador para los parámetros de la tabla/columna
                    $constrainedModifier = $modifiers->get($constrainedIndex);
                    $originalParams = is_array($constrainedModifier) ? ($constrainedModifier['parameters'] ?? []) : [];

                    // 3. Aplicar nombre corto/personalizado
                    $finalParams = $this->applyShortName($tableToFix->target_table, $colName, 'constrained', $originalParams);

                    // 4. Iniciar definición de la FK
                    $foreign = $blueprint->foreign($colName, $finalParams[2])
                        ->references($finalParams[1] ?? 'id')
                        ->on($finalParams[0] ?? $referencedTable);

                    // 5. Aplicar SOLO los modificadores que vienen DESPUÉS del constrained
                    $modifiers->slice($constrainedIndex + 1)->each(function ($modifier) use ($foreign) {
                        $method = is_array($modifier) ? $modifier['method'] : $modifier;
                        $params = is_array($modifier) ? ($modifier['parameters'] ?? []) : [];

                        // Ahora sí, ejecutamos con seguridad solo lo que el usuario encadenó a la relación
                        if (method_exists($foreign, $method)) {
                            $foreign->{$method}(...$params);
                        }
                    });
                });
            }
        }
    }

    /**
     * Lógica para obtener el nombre de la tabla referenciada
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
        return Str::plural(Str::before($column->parameters[0] ?? '', '_id'));
    }
}

