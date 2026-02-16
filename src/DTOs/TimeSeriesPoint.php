<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use Carbon\CarbonInterface;

final readonly class TimeSeriesPoint
{
    public function __construct(
        public CarbonInterface $date,
        public string $label,
        public string $key,
        public int $total,
        public int $unique,
        public float $growthPercentage,
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'label' => $this->label,
            'key' => $this->key,
            'total' => $this->total,
            'unique' => $this->unique,
            'growth_percentage' => round($this->growthPercentage, 2),
        ];
    }
}
