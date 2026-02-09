<?php

namespace Thehouseofel\Dbsync\Domain\Traits;

trait HasShortNames
{
    /**
     * Genera un nombre de índice corto y determinista para Oracle.
     */
    protected function generateShortName(string $table, string $column, string $type): string
    {
        // unq_a1b2c3d4 (12 chars)
        return substr($type, 0, 3) . '_' . substr(md5($table . ':' . $column), 0, 8);
    }

    /**
     * Ajusta los parámetros de un modificador para inyectar el nombre corto
     * si el usuario no ha definido uno manualmente.
     */
    protected function applyShortName(string $tableName, string $colName, string $method, array $params): array
    {
        $shortName = $this->generateShortName($tableName, $colName, $method);

        if ($method === 'constrained') {
            // Firma: constrained($table, $column, $indexName)
            if (count($params) === 0) return [null, null, $shortName];
            if (count($params) === 1) return [$params[0], null, $shortName];
            if (count($params) === 2) return [$params[0], $params[1], $shortName];
        } else {
            // Firma para index, unique, primary como modificadores: method($name)
            if (empty($params)) return [$shortName];
        }

        return $params;
    }
}
