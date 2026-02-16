<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;

final readonly class PeriodData
{
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
        public Granularity $granularity,
        public CalendarType $calendar,
        public string $label,
        public string $key,
        public int $days,
    ) {}

    public function toArray(): array
    {
        return [
            'start' => $this->start->toDateTimeString(),
            'end' => $this->end->toDateTimeString(),
            'granularity' => $this->granularity->value,
            'calendar' => $this->calendar->value,
            'label' => $this->label,
            'key' => $this->key,
            'days' => $this->days,
        ];
    }
}
