<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Contracts;

interface SchemaDriver
{
    public function forceDrop(string $table): void;
    public function truncate(string $table, string $column = 'id'): void;
}
