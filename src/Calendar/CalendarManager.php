<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Calendar\Adapters\GregorianAdapter;
use KarnoWeb\Viewable\Calendar\Adapters\JalaliAdapter;
use KarnoWeb\Viewable\Contracts\CalendarAdapterContract;
use KarnoWeb\Viewable\Enums\CalendarType;

class CalendarManager
{
    protected array $adapters = [];

    /**
     * Get the default calendar type.
     */
    public function getDefaultType(): CalendarType
    {
        return CalendarType::from(config('viewable.calendar.default', 'gregorian'));
    }

    /**
     * Get the configured timezone.
     */
    public function getTimezone(): string
    {
        return config('viewable.calendar.timezone', 'UTC');
    }

    /**
     * Get an adapter for the specified calendar type.
     */
    public function adapter(CalendarType|string|null $type = null): CalendarAdapterContract
    {
        if ($type === null) {
            $type = $this->getDefaultType();
        }

        if (is_string($type)) {
            $type = CalendarType::from($type);
        }

        $key = $type->value;

        if (!isset($this->adapters[$key])) {
            $this->adapters[$key] = $this->createAdapter($type);
        }

        return $this->adapters[$key];
    }

    /**
     * Create an adapter instance.
     */
    protected function createAdapter(CalendarType $type): CalendarAdapterContract
    {
        return match($type) {
            CalendarType::Gregorian => new GregorianAdapter($this->getTimezone()),
            CalendarType::Jalali => new JalaliAdapter($this->getTimezone()),
        };
    }

    /**
     * Get current date in the configured timezone.
     */
    public function now(): CarbonInterface
    {
        return Carbon::now($this->getTimezone());
    }

    /**
     * Parse a date string.
     */
    public function parse(string $date): CarbonInterface
    {
        return Carbon::parse($date, $this->getTimezone());
    }
}
