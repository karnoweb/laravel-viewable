<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use KarnoWeb\Viewable\Enums\Trend;

final readonly class GrowthData
{
    public function __construct(
        public float $percentage,
        public int $absolute,
        public Trend $trend,
        public int $currentValue,
        public int $previousValue,
    ) {}

    public static function calculate(int $current, int $previous): self
    {
        $absolute = $current - $previous;

        if ($previous === 0) {
            $percentage = $current > 0 ? 100.0 : 0.0;
        } else {
            $percentage = (($current - $previous) / $previous) * 100;
        }

        $trend = match(true) {
            $percentage > 1 => Trend::Up,
            $percentage < -1 => Trend::Down,
            default => Trend::Stable,
        };

        return new self(
            percentage: round($percentage, 2),
            absolute: $absolute,
            trend: $trend,
            currentValue: $current,
            previousValue: $previous,
        );
    }

    public function toArray(): array
    {
        return [
            'percentage' => $this->percentage,
            'absolute' => $this->absolute,
            'trend' => $this->trend->value,
            'trend_label' => $this->trend->label(),
            'trend_icon' => $this->trend->icon(),
            'current_value' => $this->currentValue,
            'previous_value' => $this->previousValue,
        ];
    }
}
