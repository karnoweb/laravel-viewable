<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;

/**
 * @method static bool record(Model $viewable, ?string $collection = null)
 * @method static int getViewsCount(Model $viewable, ?Period $period = null, ?string $collection = null)
 * @method static int getUniqueViewsCount(Model $viewable, ?Period $period = null, ?string $collection = null)
 * @method static bool hasViewed(Model $viewable, ?string $collection = null)
 * @method static AnalyticsResult getAnalytics(Model $viewable, Period $period, ?string $collection = null)
 * @method static Collection getRanking(string $modelClass, Period $period, int $limit = 10, ?string $collection = null)
 * @method static Collection getTrending(string $modelClass, Period $period, int $limit = 10, int $minViews = 10, ?string $collection = null)
 *
 * @see \KarnoWeb\Viewable\Services\ViewableService
 * @see \KarnoWeb\Viewable\Services\AnalyticsService
 */
class Viewable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'viewable';
    }
}
