<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

enum SourceVo: string
{
    case table   = 'table';
    case virtual = 'virtual';
}
