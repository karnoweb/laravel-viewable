<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;
use KarnoWeb\Viewable\Services\AnalyticsService;
use KarnoWeb\Viewable\Services\ViewableService;

trait HasViews
{
    /**
     * Get all raw view records for this model.
     */
    public function viewRecords(): MorphMany
    {
        return $this->morphMany(ViewableRecord::class, 'viewable');
    }

    /**
     * Get all aggregated views for this model.
     */
    public function viewAggregates(): MorphMany
    {
        return $this->morphMany(ViewableAggregate::class, 'viewable');
    }

    /**
     * Record a view for this model.
     */
    public function recordView(?string $collection = null): bool
    {
        return app(ViewableService::class)->record($this, $collection);
    }

    /**
     * Get total views count.
     */
    public function viewsCount(?Period $period = null, ?string $collection = null): int
    {
        return app(ViewableService::class)->getViewsCount($this, $period, $collection);
    }

    /**
     * Get unique views count.
     */
    public function uniqueViewsCount(?Period $period = null, ?string $collection = null): int
    {
        return app(ViewableService::class)->getUniqueViewsCount($this, $period, $collection);
    }

    /**
     * Check if the current visitor has viewed this model.
     */
    public function hasBeenViewed(?string $collection = null): bool
    {
        return app(ViewableService::class)->hasViewed($this, $collection);
    }

    /**
     * Get analytics for this model.
     */
    public function analytics(
        ?Period $period = null,
        ?string $collection = null,
    ): AnalyticsResult {
        $period = $period ?? Period::lastDays(30);

        return app(AnalyticsService::class)->getAnalytics($this, $period, $collection);
    }

    /**
     * Scope to order by views count.
     */
    public function scopeMostViewed(Builder $query, ?Period $period = null): Builder
    {
        $period = $period ?? Period::thisMonth();

        $aggregatesTable = (new ViewableAggregate)->getTable();

        return $query
            ->leftJoin($aggregatesTable, function ($join) use ($period) {
                $join->on($this->getTable() . '.id', '=', $aggregatesTable . '.viewable_id')
                     ->where($aggregatesTable . '.viewable_type', '=', $this->getMorphClass())
                     ->where($aggregatesTable . '.granularity', '=', Granularity::Daily->value)
                     ->where($aggregatesTable . '.period_start', '>=', $period->getStart())
                     ->where($aggregatesTable . '.period_end', '<=', $period->getEnd());
            })
            ->select($this->getTable() . '.*')
            ->selectRaw("COALESCE(SUM({$aggregatesTable}.total_views), 0) as views_sum")
            ->groupBy($this->getTable() . '.id')
            ->orderByDesc('views_sum');
    }

    /**
     * Scope to get items with minimum views.
     */
    public function scopeWithMinViews(Builder $query, int $minViews, ?Period $period = null): Builder
    {
        return $this->scopeMostViewed($query, $period)
            ->having('views_sum', '>=', $minViews);
    }
}
