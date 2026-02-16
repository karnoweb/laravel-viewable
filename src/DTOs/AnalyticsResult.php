<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use Illuminate\Support\Collection;

final readonly class AnalyticsResult
{
    /**
     * @param Collection<int, TimeSeriesPoint> $timeSeries
     */
    public function __construct(
        public PeriodData $period,
        public int $totalViews,
        public int $uniqueViews,
        public GrowthData $growth,
        public Collection $timeSeries,
        public TimeSeriesPoint $peak,
        public TimeSeriesPoint $lowest,
        public float $averageDaily,
    ) {}

    public function toArray(): array
    {
        return [
            'period' => $this->period->toArray(),
            'total_views' => $this->totalViews,
            'unique_views' => $this->uniqueViews,
            'growth' => $this->growth->toArray(),
            'time_series' => $this->timeSeries->map->toArray()->values()->all(),
            'peak' => $this->peak->toArray(),
            'lowest' => $this->lowest->toArray(),
            'average_daily' => round($this->averageDaily, 2),
        ];
    }

    /**
     * Get data formatted for chart libraries.
     */
    public function forChart(): array
    {
        return [
            'labels' => $this->timeSeries->pluck('label')->all(),
            'datasets' => [
                [
                    'name' => 'Total Views',
                    'data' => $this->timeSeries->pluck('total')->all(),
                ],
                [
                    'name' => 'Unique Views',
                    'data' => $this->timeSeries->pluck('unique')->all(),
                ],
            ],
        ];
    }
}
