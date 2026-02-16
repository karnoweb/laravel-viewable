<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Enums;

enum Trend: string
{
    case Up = 'up';
    case Down = 'down';
    case Stable = 'stable';

    public function label(): string
    {
        return match($this) {
            self::Up => 'Growing',
            self::Down => 'Declining',
            self::Stable => 'Stable',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Up => '↑',
            self::Down => '↓',
            self::Stable => '→',
        };
    }
}
