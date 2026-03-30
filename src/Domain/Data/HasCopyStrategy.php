<?php

namespace Thehouseofel\Dbsync\Domain\Data;

trait HasCopyStrategy
{
    public function validate(): void
    {
        if ($this->type === CopyStrategyTypeVo::CURSOR && $this->column !== null) {
            throw new \InvalidArgumentException('Cursor strategy does not support column.');
        }

        if (is_null($this->column) && ($this->isChunkById() || $this->isChunkOffset())) {
            throw new \InvalidArgumentException('Column must be provided for chunkById and chunk strategies.');
        }
    }

    public function isFullyManual(): bool
    {
        return $this->isCursor() || (! is_null($this->type) && ! is_null($this->column));
    }

    public function isChunkById(): bool
    {
        return $this->type?->isChunkById() ?? false;
    }

    public function isCursor(): bool
    {
        return $this->type?->isCursor() ?? false;
    }

    public function isChunkOffset(): bool
    {
        return $this->type?->isChunkOffset() ?? false;
    }
}
