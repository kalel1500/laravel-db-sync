<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Shema;

use Illuminate\Support\Facades\Schema;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncConnection;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class TableSchemaBuilder
{
    public function create(
        DbsyncConnection $connection,
        DbsyncTable      $table
    ): void
    {
        Schema::connection($connection->target_connection)
            ->create($table->target_table, function ($blueprint) use ($table) {

                foreach ($table->columns as $column) {
                    $col = $blueprint->{$column->method}(
                        $column->name,
                        ...($column->parameters ?? [])
                    );

                    foreach ($column->modifiers ?? [] as $modifier) {
                        if (method_exists($col, $modifier)) {
                            $col->{$modifier}();
                        }
                    }

                    if ($column->is_primary) {
                        $blueprint->primary($column->name);
                    }
                }
            });
    }
}

