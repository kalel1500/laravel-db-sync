<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

class RowProcessingContext
{
    public function __construct(
        public array $insertableColumns,
        public array $caseTransforms,
        public array $virtualGenerators,
    )
    {
    }
}
