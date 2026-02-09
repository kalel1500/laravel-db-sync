<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Shema;

use Illuminate\Database\Schema\Blueprint;
use Thehouseofel\Dbsync\Domain\Traits\HasShortNames;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncColumn;
use Thehouseofel\Dbsync\Infrastructure\Models\DbsyncTable;

class TableSchemaBuilder
{
    use HasShortNames;

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
        $method = $column->method;
        $tableName = $blueprint->getTable();

        if (! in_array($method, self::METHODS_WITHOUT_NAME_PARAMETER) && (empty($params) || ! is_string($params[0]))) {
            throw new \InvalidArgumentException('Column definition requires the column name as first parameter.');
        }

        if (in_array($method, ['foreign', 'index', 'unique', 'primary'])) {
            throw new \InvalidArgumentException(
                "The method '{$method}' is not allowed as a column definition. " .
                "To define constraints, use modifiers (e.g., ->unique()) for single columns " .
                "or the 'dbsync_tables' fields for composite keys."
            );
        }

        // 1. Crear la definición base (ej: $table->string('email'))
        $definition = $blueprint->{$method}(...$params);

        // 2. Aplicar modificadores
        foreach ($column->modifiers ?? [] as $modifier) {
            $mMethod = is_array($modifier) ? $modifier['method'] : $modifier;
            $mParams = is_array($modifier) ? ($modifier['parameters'] ?? []) : [];

            if (in_array($mMethod, ['index', 'unique', 'primary', 'constrained'])) {
                $mParams = $this->applyShortName($tableName, $params[0], $mMethod, $mParams);
            }

            $definition->{$mMethod}(...$mParams);
        }
    }

    protected function addPrimaryKey(Blueprint $blueprint, DbsyncTable $table): void
    {
        if ($table->primary_key) {
            $name = $this->generateShortName($blueprint->getTable(), implode('_', $table->primary_key), 'pk');
            $blueprint->primary($table->primary_key, $name);
        }
    }

    protected function addUniqueKeys(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->unique_keys ?? [] as $columns) {
            if (! is_array($columns)) {
                throw new \InvalidArgumentException(
                    "Table '{$table->target_table}' has an invalid unique_keys format. " .
                    "Each entry must be an array of columns (e.g., [['email'], ['field1', 'field2']])."
                );
            }

            $name = $this->generateShortName($blueprint->getTable(), implode('_', $columns), 'unq');
            $blueprint->unique($columns, $name);
        }
    }

    protected function addIndexes(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->indexes ?? [] as $columns) {
            if (! is_array($columns)) {
                throw new \InvalidArgumentException(
                    "Table '{$table->target_table}' has an invalid indexes format. " .
                    "Each entry must be an array of columns (e.g., [['category_id'], ['active', 'created_at']])."
                );
            }

            $name = $this->generateShortName($blueprint->getTable(), implode('_', $columns), 'idx');
            $blueprint->index($columns, $name);
        }
    }
}

