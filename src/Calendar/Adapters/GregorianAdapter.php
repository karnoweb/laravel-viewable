<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar\Adapters;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Contracts\CalendarAdapterContract;
use KarnoWeb\Viewable\Enums\CalendarType;

class GregorianAdapter implements CalendarAdapterContract
{
    public function __construct(
        protected string $timezone = 'UTC',
    ) {}

    public function getType(): CalendarType
    {
        return CalendarType::Gregorian;
    }

    public function format(CarbonInterface $date, string $format): string
    {
        return $date->format($format);
    }

    public function startOfDay(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfDay();
    }

    public function endOfDay(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfDay();
    }

    public function startOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfWeek(config('viewable.calendar.week_starts_on', 0));
    }

    public function endOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfWeek(config('viewable.calendar.week_starts_on', 0));
    }

    public function startOfMonth(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfMonth();
    }

    public function endOfMonth(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfMonth();
    }

    public function startOfYear(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfYear();
    }

    public function endOfYear(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfYear();
    }

    public function createDate(int $year, int $month, int $day): CarbonInterface
    {
        return Carbon::create($year, $month, $day, 0, 0, 0, $this->timezone);
    }

    public function getPeriodKey(CarbonInterface $date, string $granularity): string
    {
        return match($granularity) {
            'hourly' => $date->format('Y-m-d-H'),
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->format('Y-W'),
            'monthly' => $date->format('Y-m'),
            'yearly' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    public function getPeriodLabel(CarbonInterface $date, string $granularity): string
    {
        return match($granularity) {
            'hourly' => $date->format('M d, H:00'),
            'daily' => $date->format('M d, Y'),
            'weekly' => 'Week ' . $date->format('W, Y'),
            'monthly' => $date->format('F Y'),
            'yearly' => $date->format('Y'),
            default => $date->format('M d, Y'),
        };
    }
}
