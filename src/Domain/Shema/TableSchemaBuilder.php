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
        $method = $column->method;
        $tableName = $blueprint->getTable();

        if (! in_array($method, self::METHODS_WITHOUT_NAME_PARAMETER) && (empty($params) || ! is_string($params[0]))) {
            throw new \InvalidArgumentException('Column definition requires the column name as first parameter.');
        }

        // 1. Crear la definición base (ej: $table->string('email'))
        $definition = $blueprint->{$method}(...$params);

        // 2. Aplicar modificadores
        foreach ($column->modifiers ?? [] as $modifier) {
            $mMethod = is_array($modifier) ? $modifier['method'] : $modifier;
            $mParams = is_array($modifier) ? ($modifier['parameters'] ?? []) : [];

            if (in_array($mMethod, ['index', 'unique', 'primary', 'constrained'])) {
                // Generamos el nombre corto (el que salva a Oracle)
                $shortName = $this->generateShortName($tableName, $params[0], $mMethod);

                if ($mMethod === 'constrained') {
                    // constrained($table = null, $column = null, $indexName = null) || Necesitamos asegurar que el nombre vaya en la tercera posición
                    if (count($mParams) === 0) {
                        $mParams = [null, null, $shortName];
                    } elseif (count($mParams) === 1) {
                        $mParams[] = null;
                        $mParams[] = $shortName;
                    } elseif (count($mParams) === 2) {
                        $mParams[] = $shortName;
                    }
                    // Si ya tiene 3 parámetros, el usuario ya puso un nombre, lo respetamos.
                } else {
                    // index, unique, primary como MODIFICADORES || El nombre es el PRIMER parámetro: ->unique('nombre_corto')
                    if (empty($mParams)) {
                        $mParams = [$shortName];
                    }
                }
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
        foreach ($table->unique_keys ?? [] as $unique) {
            $name = $this->generateShortName($blueprint->getTable(), implode('_', $unique), 'unq');
            $blueprint->unique($unique, $name);
        }
    }

    protected function addIndexes(Blueprint $blueprint, DbsyncTable $table): void
    {
        foreach ($table->indexes ?? [] as $index) {
            $name = $this->generateShortName($blueprint->getTable(), implode('_', $index), 'idx');
            $blueprint->index($index, $name);
        }
    }

    protected function generateShortName(string $table, string $column, string $type): string
    {
        // 12 caracteres en total)
        return substr($type, 0, 3) . '_' . substr(md5($table . $column), 0, 8);
    }
}

