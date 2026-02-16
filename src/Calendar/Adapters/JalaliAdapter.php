<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar\Adapters;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Contracts\CalendarAdapterContract;
use KarnoWeb\Viewable\Enums\CalendarType;
use Morilog\Jalali\Jalalian;

class JalaliAdapter implements CalendarAdapterContract
{
    protected array $monthNames = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    public function __construct(
        protected string $timezone = 'Asia/Tehran',
    ) {}

    public function getType(): CalendarType
    {
        return CalendarType::Jalali;
    }

    public function format(CarbonInterface $date, string $format): string
    {
        return Jalalian::fromCarbon($date)->format($format);
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
        // In Jalali calendar, week starts on Saturday (6)
        return $date->copy()->startOfWeek(Carbon::SATURDAY);
    }

    public function endOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfWeek(Carbon::SATURDAY);
    }

    public function startOfMonth(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-%02d-01', $jalali->getYear(), $jalali->getMonth()))
            ->toCarbon()
            ->startOfDay();
    }

    public function endOfMonth(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        $daysInMonth = $jalali->getMonthDays();

        return Jalalian::fromFormat('Y-m-d', sprintf('%d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $daysInMonth))
            ->toCarbon()
            ->endOfDay();
    }

    public function startOfYear(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-01-01', $jalali->getYear()))
            ->toCarbon()
            ->startOfDay();
    }

    public function endOfYear(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        $isLeap = $jalali->isLeapYear();
        $lastDay = $isLeap ? 30 : 29;

        return Jalalian::fromFormat('Y-m-d', sprintf('%d-12-%02d', $jalali->getYear(), $lastDay))
            ->toCarbon()
            ->endOfDay();
    }

    public function createDate(int $year, int $month, int $day): CarbonInterface
    {
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-%02d-%02d', $year, $month, $day))
            ->toCarbon($this->timezone);
    }

    public function getPeriodKey(CarbonInterface $date, string $granularity): string
    {
        $jalali = Jalalian::fromCarbon($date);

        return match($granularity) {
            'hourly' => sprintf('%d-%02d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $jalali->getDay(), $date->hour),
            'daily' => sprintf('%d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $jalali->getDay()),
            'weekly' => sprintf('%d-W%02d', $jalali->getYear(), $jalali->getWeekOfYear()),
            'monthly' => sprintf('%d-%02d', $jalali->getYear(), $jalali->getMonth()),
            'yearly' => (string) $jalali->getYear(),
            default => sprintf('%d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $jalali->getDay()),
        };
    }

    public function getPeriodLabel(CarbonInterface $date, string $granularity): string
    {
        $jalali = Jalalian::fromCarbon($date);
        $monthName = $this->monthNames[$jalali->getMonth()];

        return match($granularity) {
            'hourly' => sprintf('%d %s، ساعت %02d', $jalali->getDay(), $monthName, $date->hour),
            'daily' => sprintf('%d %s %d', $jalali->getDay(), $monthName, $jalali->getYear()),
            'weekly' => sprintf('هفته %d، %d', $jalali->getWeekOfYear(), $jalali->getYear()),
            'monthly' => sprintf('%s %d', $monthName, $jalali->getYear()),
            'yearly' => (string) $jalali->getYear(),
            default => sprintf('%d %s %d', $jalali->getDay(), $monthName, $jalali->getYear()),
        };
    }
}
