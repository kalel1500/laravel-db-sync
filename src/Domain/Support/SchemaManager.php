<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Thehouseofel\Dbsync\Domain\Contracts\SchemaDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\MariaDbDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\MySqlDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\OracleDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\PostgresDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\SQLiteDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\SqlServerDriver;

class SchemaManager
{
    protected ?SchemaDriver $driverInstance = null;

    protected array $driversMap = [
        'sqlite'  => SQLiteDriver::class,
        'mysql'   => MySqlDriver::class,
        'mariadb' => MariaDbDriver::class,
        'pgsql'   => PostgresDriver::class,
        'sqlsrv'  => SqlServerDriver::class,
        'oracle'  => OracleDriver::class,
        'oci8'    => OracleDriver::class,
    ];

    public function __construct(
        protected Connection $connection
    )
    {
    }

    protected function driver(): SchemaDriver
    {
        if ($this->driverInstance) {
            return $this->driverInstance;
        }

        $name  = $this->connection->getDriverName();
        $class = $this->driversMap[$name] ?? throw new \RuntimeException("Driver $name no soportado.");

        return $this->driverInstance = new $class($this->connection);
    }

    /**
     * Borra una tabla ignorando restricciones de integridad según el driver.
     */
    public function forceDrop(string $table): void
    {
        if (! $this->connection->getSchemaBuilder()->hasTable($table)) {
            return;
        }
        $this->driver()->forceDrop($table);
    }

    /**
     * Vacía varias tablas y resetea los contadores de ID autoincrementales.
     */
    public function truncate(array $tables): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                $table  = is_string($table) ? $table : $table['name'];
                $column = is_string($table) ? 'id' : $table['column'] ?? 'id';
                $this->driver()->truncate($table, $column);
            }
        } catch (\Throwable $exception) {
            // En caso de error, intentamos truncar sin resetear los contadores.
            foreach ($tables as $table) {
                $table = is_string($table) ? $table : $table['name'];
                $this->connection->table($table)->truncate();
            }
            $this->tryEnableForeignKeyConstraints($schema, $tables);
        }

        $this->tryEnableForeignKeyConstraints($schema, $tables);
    }

    private function tryEnableForeignKeyConstraints(Builder $schema, array $tables): void
    {
        try {
            $schema->enableForeignKeyConstraints();
        } catch (\Throwable $e) {
            $tableNames = implode(', ', array_map(fn($t) => is_string($t) ? $t : $t['name'], $tables));
            throw new \RuntimeException(
                "CRITICAL: Constraints could not be re-enabled after truncate. " .
                "Your database schema might be inconsistent. Ensure you included all related tables: " .
                "$tableNames. Error: {$e->getMessage()}"
            );
        }
    }

}
