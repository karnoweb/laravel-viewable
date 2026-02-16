<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Contracts;

use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Enums\CalendarType;

interface CalendarAdapterContract
{
    /**
     * Get the calendar type.
     */
    public function getType(): CalendarType;

    /**
     * Format a date according to this calendar.
     */
    public function format(CarbonInterface $date, string $format): string;

    /**
     * Get the start of a day.
     */
    public function startOfDay(CarbonInterface $date): CarbonInterface;

    /**
     * Get the end of a day.
     */
    public function endOfDay(CarbonInterface $date): CarbonInterface;

    /**
     * Get the start of a week.
     */
    public function startOfWeek(CarbonInterface $date): CarbonInterface;

    /**
     * Get the end of a week.
     */
    public function endOfWeek(CarbonInterface $date): CarbonInterface;

    /**
     * Get the start of a month.
     */
    public function startOfMonth(CarbonInterface $date): CarbonInterface;

    /**
     * Get the end of a month.
     */
    public function endOfMonth(CarbonInterface $date): CarbonInterface;

    /**
     * Get the start of a year.
     */
    public function startOfYear(CarbonInterface $date): CarbonInterface;

    /**
     * Get the end of a year.
     */
    public function endOfYear(CarbonInterface $date): CarbonInterface;

    /**
     * Create a date from year, month, day.
     */
    public function createDate(int $year, int $month, int $day): CarbonInterface;

    /**
     * Get period key for a date (e.g., "2024-01-15" or "1402-10-25").
     */
    public function getPeriodKey(CarbonInterface $date, string $granularity): string;

    /**
     * Get human-readable label for a period.
     */
    public function getPeriodLabel(CarbonInterface $date, string $granularity): string;
}
