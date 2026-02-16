<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\DTOs\PeriodData;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;

class Period
{
    protected CarbonInterface $start;
    protected CarbonInterface $end;
    protected CalendarType $calendar;
    protected Granularity $granularity;

    public function __construct(
        CarbonInterface $start,
        CarbonInterface $end,
        CalendarType $calendar = CalendarType::Gregorian,
        Granularity $granularity = Granularity::Daily,
    ) {
        $this->start = $start;
        $this->end = $end;
        $this->calendar = $calendar;
        $this->granularity = $granularity;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getStart(): CarbonInterface
    {
        return $this->start;
    }

    public function getEnd(): CarbonInterface
    {
        return $this->end;
    }

    public function getCalendar(): CalendarType
    {
        return $this->calendar;
    }

    public function getGranularity(): Granularity
    {
        return $this->granularity;
    }

    public function getDays(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function calendar(CalendarType $calendar): self
    {
        $this->calendar = $calendar;
        return $this;
    }

    public function granularity(Granularity $granularity): self
    {
        $this->granularity = $granularity;
        return $this;
    }

    public function asJalali(): self
    {
        $this->calendar = CalendarType::Jalali;
        return $this;
    }

    public function asGregorian(): self
    {
        $this->calendar = CalendarType::Gregorian;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Static constructors - Gregorian
    // -------------------------------------------------------------------------

    public static function today(): self
    {
        $now = self::now();
        return new self($now->copy()->startOfDay(), $now->copy()->endOfDay());
    }

    public static function yesterday(): self
    {
        $yesterday = self::now()->subDay();
        return new self($yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay());
    }

    public static function thisWeek(): self
    {
        $now = self::now();
        return new self(
            $now->copy()->startOfWeek(config('viewable.calendar.week_starts_on', 6)),
            $now->copy()->endOfWeek(config('viewable.calendar.week_starts_on', 6)),
            granularity: Granularity::Daily,
        );
    }

    public static function lastWeek(): self
    {
        $now = self::now()->subWeek();
        return new self(
            $now->copy()->startOfWeek(config('viewable.calendar.week_starts_on', 6)),
            $now->copy()->endOfWeek(config('viewable.calendar.week_starts_on', 6)),
            granularity: Granularity::Daily,
        );
    }

    public static function thisMonth(): self
    {
        $now = self::now();
        return new self(
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
            granularity: Granularity::Daily,
        );
    }

    public static function lastMonth(): self
    {
        $now = self::now()->subMonth();
        return new self(
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
            granularity: Granularity::Daily,
        );
    }

    public static function thisYear(): self
    {
        $now = self::now();
        return new self(
            $now->copy()->startOfYear(),
            $now->copy()->endOfYear(),
            granularity: Granularity::Monthly,
        );
    }

    public static function lastDays(int $days): self
    {
        $now = self::now();
        return new self(
            $now->copy()->subDays($days - 1)->startOfDay(),
            $now->copy()->endOfDay(),
            granularity: Granularity::Daily,
        );
    }

    public static function lastHours(int $hours): self
    {
        $now = self::now();
        return new self(
            $now->copy()->subHours($hours),
            $now,
            granularity: Granularity::Hourly,
        );
    }

    public static function between(CarbonInterface|string $start, CarbonInterface|string $end): self
    {
        $manager = app(CalendarManager::class);

        $start = is_string($start) ? $manager->parse($start) : $start;
        $end = is_string($end) ? $manager->parse($end) : $end;

        return new self($start, $end);
    }

    // -------------------------------------------------------------------------
    // Static constructors - Jalali
    // -------------------------------------------------------------------------

    public static function jalaliToday(): self
    {
        return self::today()->asJalali();
    }

    public static function jalaliThisWeek(): self
    {
        return self::thisWeek()->asJalali();
    }

    public static function jalaliThisMonth(): self
    {
        return self::thisMonth()->asJalali();
    }

    public static function jalaliMonth(int $year, int $month): self
    {
        $adapter = app(CalendarManager::class)->adapter(CalendarType::Jalali);
        $start = $adapter->createDate($year, $month, 1);
        $end = $adapter->endOfMonth($start);

        return new self($start, $end, CalendarType::Jalali, Granularity::Daily);
    }

    public static function jalaliYear(int $year): self
    {
        $adapter = app(CalendarManager::class)->adapter(CalendarType::Jalali);
        $start = $adapter->createDate($year, 1, 1);
        $end = $adapter->endOfYear($start);

        return new self($start, $end, CalendarType::Jalali, Granularity::Monthly);
    }

    // -------------------------------------------------------------------------
    // Previous period (for comparison)
    // -------------------------------------------------------------------------

    public function previousPeriod(): self
    {
        $days = $this->getDays();

        return new self(
            $this->start->copy()->subDays($days),
            $this->start->copy()->subDay()->endOfDay(),
            $this->calendar,
            $this->granularity,
        );
    }

    // -------------------------------------------------------------------------
    // Conversion to DTO
    // -------------------------------------------------------------------------

    public function toData(): PeriodData
    {
        $adapter = app(CalendarManager::class)->adapter($this->calendar);

        return new PeriodData(
            start: $this->start,
            end: $this->end,
            granularity: $this->granularity,
            calendar: $this->calendar,
            label: $adapter->getPeriodLabel($this->start, $this->granularity->value),
            key: $adapter->getPeriodKey($this->start, $this->granularity->value),
            days: $this->getDays(),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected static function now(): CarbonInterface
    {
        return app(CalendarManager::class)->now();
    }
}
