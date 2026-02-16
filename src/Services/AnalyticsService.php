<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use KarnoWeb\Viewable\Branch\BranchManager;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;
use KarnoWeb\Viewable\DTOs\GrowthData;
use KarnoWeb\Viewable\DTOs\TimeSeriesPoint;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;

class AnalyticsService
{
    public function __construct(
        protected CalendarManager $calendarManager,
        protected BranchManager $branchManager,
    ) {}

    /**
     * Get analytics for a viewable model.
     */
    public function getAnalytics(
        Model $viewable,
        Period $period,
        ?string $collection = null,
    ): AnalyticsResult {
        $collection = $collection ?? config('viewable.collections.default', 'default');

        // Get time series data
        $timeSeries = $this->getTimeSeries($viewable, $period, $collection);

        // Calculate totals
        $totalViews = $timeSeries->sum('total');
        $uniqueViews = $timeSeries->sum('unique');

        // Get previous period for comparison
        $previousPeriod = $period->previousPeriod();
        $previousTimeSeries = $this->getTimeSeries($viewable, $previousPeriod, $collection);
        $previousTotal = $previousTimeSeries->sum('total');

        // Calculate growth
        $growth = GrowthData::calculate($totalViews, $previousTotal);

        // Find peak and lowest points
        $peak = $timeSeries->sortByDesc('total')->first();
        $lowest = $timeSeries->sortBy('total')->first();

        // Calculate average
        $days = $period->getDays();
        $averageDaily = $days > 0 ? $totalViews / $days : 0;

        return new AnalyticsResult(
            period: $period->toData(),
            totalViews: $totalViews,
            uniqueViews: $uniqueViews,
            growth: $growth,
            timeSeries: $timeSeries,
            peak: $peak ?? $this->createEmptyPoint($period),
            lowest: $lowest ?? $this->createEmptyPoint($period),
            averageDaily: $averageDaily,
        );
    }

    /**
     * Get time series data for a period.
     *
     * @return Collection<int, TimeSeriesPoint>
     */
    public function getTimeSeries(
        Model $viewable,
        Period $period,
        string $collection,
    ): Collection {
        $adapter = $this->calendarManager->adapter($period->getCalendar());
        $granularity = $period->getGranularity();

        // Get aggregated data
        $aggregates = ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity($granularity)
            ->forCalendar($period->getCalendar())
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->orderBy('period_start')
            ->get()
            ->keyBy('period_key');

        // Generate all points in the period
        $points = collect();
        $current = $period->getStart()->copy();
        $previousTotal = 0;

        while ($current <= $period->getEnd()) {
            $key = $adapter->getPeriodKey($current, $granularity->value);
            $label = $adapter->getPeriodLabel($current, $granularity->value);

            $aggregate = $aggregates->get($key);
            $total = $aggregate?->total_views ?? 0;
            $unique = $aggregate?->unique_views ?? 0;

            // Add today's data from raw records if applicable
            if ($this->isToday($current) && config('viewable.analytics.include_today', true)) {
                $todayData = $this->getTodayData($viewable, $collection);
                $total += $todayData['total'];
                $unique += $todayData['unique'];
            }

            $growthPct = $previousTotal > 0
                ? (($total - $previousTotal) / $previousTotal) * 100
                : 0;

            $points->push(new TimeSeriesPoint(
                date: $current->copy(),
                label: $label,
                key: $key,
                total: $total,
                unique: $unique,
                growthPercentage: round($growthPct, 2),
            ));

            $previousTotal = $total;
            $current = $this->advanceDate($current, $granularity);
        }

        return $points;
    }

    /**
     * Get ranking of most viewed models.
     *
     * @return Collection<int, array{model: Model, total_views: int, unique_views: int}>
     */
    public function getRanking(
        string $modelClass,
        Period $period,
        int $limit = 10,
        ?string $collection = null,
    ): Collection {
        $collection = $collection ?? config('viewable.collections.default', 'default');

        $results = ViewableAggregate::query()
            ->select('viewable_id')
            ->selectRaw('SUM(total_views) as total_views')
            ->selectRaw('SUM(unique_views) as unique_views')
            ->where('viewable_type', (new $modelClass)->getMorphClass())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forCalendar($period->getCalendar())
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->groupBy('viewable_id')
            ->orderByDesc('total_views')
            ->limit($limit)
            ->get();

        // Load the actual models
        $modelIds = $results->pluck('viewable_id');
        $models = $modelClass::whereIn('id', $modelIds)->get()->keyBy('id');

        return $results->map(function ($row) use ($models) {
            return [
                'model' => $models->get($row->viewable_id),
                'total_views' => (int) $row->total_views,
                'unique_views' => (int) $row->unique_views,
            ];
        })->filter(fn ($item) => $item['model'] !== null);
    }

    /**
     * Get trending models (fastest growing).
     */
    public function getTrending(
        string $modelClass,
        Period $period,
        int $limit = 10,
        int $minViews = 10,
        ?string $collection = null,
    ): Collection {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        $previousPeriod = $period->previousPeriod();

        // Get current period views
        $currentViews = $this->getViewsByModel($modelClass, $period, $collection);

        // Get previous period views
        $previousViews = $this->getViewsByModel($modelClass, $previousPeriod, $collection);

        // Calculate growth and sort
        return $currentViews
            ->filter(fn ($views) => $views >= $minViews)
            ->map(function ($currentTotal, $modelId) use ($previousViews) {
                $previousTotal = $previousViews->get($modelId, 0);
                return [
                    'model_id' => $modelId,
                    'current' => $currentTotal,
                    'previous' => $previousTotal,
                    'growth' => GrowthData::calculate($currentTotal, $previousTotal),
                ];
            })
            ->sortByDesc(fn ($item) => $item['growth']->percentage)
            ->take($limit)
            ->map(function ($item) use ($modelClass) {
                $item['model'] = $modelClass::find($item['model_id']);
                return $item;
            })
            ->filter(fn ($item) => $item['model'] !== null);
    }

    // -------------------------------------------------------------------------
    // Protected methods
    // -------------------------------------------------------------------------

    protected function isToday($date): bool
    {
        return $date->toDateString() === $this->calendarManager->now()->toDateString();
    }

    protected function getTodayData(Model $viewable, string $collection): array
    {
        $today = $this->calendarManager->now()->startOfDay();

        $query = ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('viewed_at', '>=', $today)
            ->forBranch();

        return [
            'total' => (clone $query)->count(),
            'unique' => (clone $query)->distinct('visitor_key')->count('visitor_key'),
        ];
    }

    protected function advanceDate($date, Granularity $granularity)
    {
        return match($granularity) {
            Granularity::Hourly => $date->addHour(),
            Granularity::Daily => $date->addDay(),
            Granularity::Weekly => $date->addWeek(),
            Granularity::Monthly => $date->addMonth(),
            Granularity::Yearly => $date->addYear(),
        };
    }

    protected function createEmptyPoint(Period $period): TimeSeriesPoint
    {
        return new TimeSeriesPoint(
            date: $period->getStart(),
            label: '',
            key: '',
            total: 0,
            unique: 0,
            growthPercentage: 0,
        );
    }

    protected function getViewsByModel(
        string $modelClass,
        Period $period,
        string $collection,
    ): Collection {
        return ViewableAggregate::query()
            ->select('viewable_id')
            ->selectRaw('SUM(total_views) as total_views')
            ->where('viewable_type', (new $modelClass)->getMorphClass())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->groupBy('viewable_id')
            ->pluck('total_views', 'viewable_id');
    }
}
