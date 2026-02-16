<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Contracts;

use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

interface SchemaDriver
{
    public function forceDrop(string $table): void;

    public function truncate(string $table, string $column = 'id'): void;

    public function insertBulk(string $targetTable, array $rows): void;

    public function insertRowByRow(string $targetTable, array $rows): void;

    public function insertAuto(DbsyncTable $table, string $targetTable, array $rows): void;
}
