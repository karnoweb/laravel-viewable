<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Enums;

enum CalendarType: string
{
    case Gregorian = 'gregorian';
    case Jalali = 'jalali';

    public function label(): string
    {
        return match($this) {
            self::Gregorian => 'Gregorian',
            self::Jalali => 'Jalali (Shamsi)',
        };
    }
}
