<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Data;

readonly class ResolvedStrategyDto
{
    use HasCopyStrategy;

    public function __construct(
        public CopyStrategyTypeVo $type,
        public ?string            $column,
    )
    {
        $this->validate();
    }
}
