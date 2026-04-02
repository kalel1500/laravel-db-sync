<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Contracts;

use Illuminate\Database\Connection;
use Thehouseofel\Dbsync\Domain\Support\SchemaConnection;

interface SchemaFactory
{
    public function connection(Connection|string|null $connection = null): SchemaConnection;
}
