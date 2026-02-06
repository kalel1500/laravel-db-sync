<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Shema;

use Illuminate\Database\Schema\Blueprint;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncColumn;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class TableSchemaBuilder
{
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

        if (! in_array($column->method, self::METHODS_WITHOUT_NAME_PARAMETER) && (empty($params) || ! is_string($params[0]))) {
            throw new \InvalidArgumentException('Column definition requires the column name as first parameter.');
        }

        $definition = $blueprint->{$column->method}(...$params);

        foreach ($column->modifiers ?? [] as $modifier) {
            if (is_string($modifier)) {
                $definition->{$modifier}();
            } elseif (is_array($modifier)) {
                $definition->{$modifier['method']}(...($modifier['parameters'] ?? []));
            }
        }
    }

    protected function addPrimaryKey(Blueprint $blueprint, DbsyncTable $table): void
    {
        if ($table->primary_key) {
            $blueprint->primary($table->primary_key);
        }
    }

    protected function addUniqueKeys(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->unique_keys ?? [] as $unique) {
            $blueprint->unique($unique);
        }
    }

    protected function addIndexes(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->indexes ?? [] as $index) {
            $blueprint->index($index);
        }
    }
}

