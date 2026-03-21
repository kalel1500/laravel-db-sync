<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

enum ChunkMethodVo: string
{
    case chunk     = 'chunk';
    case chunkById = 'chunkById';

    public function isChunkById(): bool
    {
        return $this === self::chunkById;
    }
}
