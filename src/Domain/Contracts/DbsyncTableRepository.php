<?php

namespace Thehouseofel\Dbsync\Domain\Contracts;

interface DbsyncTableRepository
{
    public function getForTable(int $tableId);

    public function getForDatabase(int $databaseId);

    public function getForConnection(int $connectionId);

    public function getForAll();
}
