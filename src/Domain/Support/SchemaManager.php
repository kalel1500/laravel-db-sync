<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Thehouseofel\Dbsync\Domain\Contracts\SchemaDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\MariaDbDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\MySqlDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\OracleDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\PostgresDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\SQLiteDriver;
use Thehouseofel\Dbsync\Domain\Support\Drivers\SqlServerDriver;

class SchemaManager
{
    protected Connection    $connection;
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

    public function __construct()
    {
        $this->connection = DB::connection(config('database.default'));
    }

    protected function driver(): SchemaDriver
    {
        if ($this->driverInstance) {
            return $this->driverInstance;
        }

        $name  = $this->connection->getDriverName();
        $class = $this->driversMap[$name] ?? throw new \RuntimeException("Driver $name no soportado para SchemaManager.");

        return $this->driverInstance = new $class($this->connection);
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
        if (! $this->connection->getSchemaBuilder()->hasTable($table)) {
            return;
        }
        $this->driver()->forceDrop($table);
    }
}
