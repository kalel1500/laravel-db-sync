<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

readonly class ResolvedPrimaryDto
{
    public function __construct(
        public string        $name,
        public ChunkMethodVo $method,
    )
    {
    }
}
