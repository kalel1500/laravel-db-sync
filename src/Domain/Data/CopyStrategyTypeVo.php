<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

enum CopyStrategyTypeVo: string
{
    case CHUNK_BY_ID  = 'chunkById';
    case CURSOR       = 'cursor';
    case CHUNK_OFFSET = 'chunk';

    public function isChunkById(): bool
    {
        return $this === self::CHUNK_BY_ID;
    }

    public function isCursor(): bool
    {
        return $this === self::CURSOR;
    }

    public function isChunkOffset(): bool
    {
        return $this === self::CHUNK_OFFSET;
    }
}
