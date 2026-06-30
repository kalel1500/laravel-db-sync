<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Thehouseofel\Dbsync\Domain\Contracts\SchemaDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\DB2Driver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\MariaDbDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\MySqlDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\OracleDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\PostgresDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\SQLiteDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\SqlServerDriver;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class SchemaConnection
{
    protected ?SchemaDriver $driverInstance = null;

    protected array $driversMap = [
        'sqlite'  => SQLiteDriver::class,
        'mysql'   => MySqlDriver::class,
        'mariadb' => MariaDbDriver::class,
        'db2'     => DB2Driver::class,
        'ibmi'    => DB2Driver::class,
        'ibm'     => DB2Driver::class,
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

    public function isOracle(): bool
    {
        return $this->driver()->getClass() === OracleDriver::class;
    }

    public function forceDrop(string $table): void
    {
        if (! $this->connection->getSchemaBuilder()->hasTable($table)) {
            return;
        }
        $this->driver()->forceDrop($table);
    }

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
        } finally {
            $this->tryEnableForeignKeyConstraints($schema, $tables);
        }
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

    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $this->driver()->syncIdentity($table, $column);
    }

    public function disableBuffer(): void
    {
        $this->driver()->disableBuffer();
    }

    public function enableBuffer(): void
    {
        $this->driver()->enableBuffer();
    }
}
