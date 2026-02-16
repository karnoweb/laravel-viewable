<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;

interface ViewableContract
{
    /**
     * Get all view records for this model.
     */
    public function viewRecords(): MorphMany;

    /**
     * Get all aggregated views for this model.
     */
    public function viewAggregates(): MorphMany;

    /**
     * Record a view for this model.
     */
    public function recordView(?string $collection = null): bool;

    /**
     * Get total views count.
     */
    public function viewsCount(?Period $period = null, ?string $collection = null): int;

    /**
     * Get unique views count.
     */
    public function uniqueViewsCount(?Period $period = null, ?string $collection = null): int;

    /**
     * Get analytics for this model.
     */
    public function analytics(): AnalyticsResult;
}
