<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support\Drivers;

use Composer\InstalledVersions;

class Db2Driver extends BaseDriver
{
    protected function validateVersion(): void
    {
        $package = 'easi-power/laravel-db2';

        if (! InstalledVersions::isInstalled($package)) {
            throw new \RuntimeException("The package '$package' is not installed. Please install it to use the DB2 driver.");
        }

        $version = InstalledVersions::getVersion($package);

        if ($version === null || version_compare($version, '13.0', '<')) {
            throw new \RuntimeException("The installed version of '$package' is not supported. Please install version ^13.0.");
        }
    }

    public function forceDrop(string $table): void
    {
        // DB2 drops the foreign keys that reference the table along with the table.
        $this->connection->getSchemaBuilder()->drop($table);
    }

    public function truncate(string $table, string $column = 'id'): void
    {
        $this->connection->statement("TRUNCATE TABLE {$this->wrapTable($table)} RESTART IDENTITY IMMEDIATE");
    }

    public function syncIdentity(string $table, string $column = 'id'): void
    {
        $max  = $this->connection->table($table)->max($column);
        $next = ($max ?? 0) + 1;

        $this->connection->statement(
            "ALTER TABLE {$this->wrapTable($table)} " .
            "ALTER COLUMN {$this->wrapColumn($column)} RESTART WITH {$next}"
        );
    }
}
