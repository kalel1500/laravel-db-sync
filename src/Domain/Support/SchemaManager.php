<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

/**
 * @mixin \Thehouseofel\Dbsync\Domain\Support\SchemaConnection
 */
class SchemaManager
{
    /** @var SchemaConnection[] */
    protected array $resolved = [];

    public function __construct(
        protected DatabaseManager $db
    )
    {
    }

    public function connection(Connection|string|null $connection = null): SchemaConnection
    {
        $name = match (true) {
            $connection instanceof Connection => $connection->getName(),
            is_null($connection)              => $this->db->getDefaultConnection(),
            default                           => $connection,
        };

        return $this->resolved[$name] ??= new SchemaConnection(
            connection: ($connection instanceof Connection) ? $connection : $this->db->connection($name)
        );
    }

    public function __call($method, $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}
