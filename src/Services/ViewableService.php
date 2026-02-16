<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use KarnoWeb\Viewable\Branch\BranchManager;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\ViewData;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Events\ViewRecorded;
use KarnoWeb\Viewable\Jobs\RecordViewJob;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;

class ViewableService
{
    public function __construct(
        protected VisitorService $visitorService,
        protected CooldownService $cooldownService,
        protected BranchManager $branchManager,
        protected CalendarManager $calendarManager,
    ) {}

    /**
     * Record a view for a model.
     */
    public function record(Model $viewable, ?string $collection = null): bool
    {
        // Check if bot should be ignored
        if (config('viewable.visitor.bot_detection.ignore_bots', true) && $this->visitorService->isBot()) {
            return false;
        }

        $collection = $collection ?? $this->detectCollection();
        $visitorKey = $this->visitorService->getVisitorKey();

        // Check cooldown
        if (!$this->cooldownService->canRecord($viewable, $visitorKey, $collection)) {
            return false;
        }

        $viewData = ViewData::fromRequest($viewable, $collection);

        // Process synchronously or via queue
        if (config('viewable.performance.queue.enabled', false)) {
            RecordViewJob::dispatch($viewData);
        } else {
            $this->processView($viewData);
        }

        // Mark cooldown
        $this->cooldownService->markRecorded($viewable, $visitorKey, $collection);

        return true;
    }

    /**
     * Process and store the view.
     */
    public function processView(ViewData $viewData): void
    {
        // Store the raw record
        ViewableRecord::create($viewData->toArray());

        // Update counter cache on the model if configured
        if (config('viewable.counter_cache.enabled', true)) {
            $this->updateCounterCache($viewData->viewable);
        }

        // Dispatch event
        event(new ViewRecorded($viewData));
    }

    /**
     * Get total views count for a model.
     */
    public function getViewsCount(
        Model $viewable,
        ?Period $period = null,
        ?string $collection = null,
    ): int {
        $collection = $collection ?? config('viewable.collections.default', 'default');

        if ($period === null) {
            return $this->getTotalViewsFromAggregates($viewable, $collection)
                 + $this->getTodayViewsFromRecords($viewable, $collection);
        }

        return $this->getViewsForPeriod($viewable, $period, $collection, 'total');
    }

    /**
     * Get unique views count for a model.
     */
    public function getUniqueViewsCount(
        Model $viewable,
        ?Period $period = null,
        ?string $collection = null,
    ): int {
        $collection = $collection ?? config('viewable.collections.default', 'default');

        if ($period === null) {
            return $this->getTotalUniqueViewsFromAggregates($viewable, $collection)
                 + $this->getTodayUniqueViewsFromRecords($viewable, $collection);
        }

        return $this->getViewsForPeriod($viewable, $period, $collection, 'unique');
    }

    /**
     * Check if a visitor has already viewed the model.
     */
    public function hasViewed(Model $viewable, ?string $collection = null): bool
    {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        $visitorKey = $this->visitorService->getVisitorKey();

        return ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('visitor_key', $visitorKey)
            ->forBranch()
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Protected methods
    // -------------------------------------------------------------------------

    protected function detectCollection(): string
    {
        if (!config('viewable.collections.auto_detect', true)) {
            return config('viewable.collections.default', 'default');
        }

        $guards = config('viewable.collections.guards', []);

        foreach ($guards as $guard => $collection) {
            if (auth()->guard($guard)->check()) {
                return $collection;
            }
        }

        // Detect from request
        if (request()->expectsJson() || request()->is('api/*')) {
            return 'api';
        }

        return 'web';
    }

    protected function updateCounterCache(Model $viewable): void
    {
        $columns = config('viewable.counter_cache.columns', ['view_count']);
        $attributes = $viewable->getAttributes();

        foreach ($columns as $column) {
            if (array_key_exists($column, $attributes)) {
                $viewable->increment($column);
                break;
            }
        }
    }

    protected function getTotalViewsFromAggregates(Model $viewable, string $collection): int
    {
        return (int) ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forBranch()
            ->sum('total_views');
    }

    protected function getTotalUniqueViewsFromAggregates(Model $viewable, string $collection): int
    {
        return (int) ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forBranch()
            ->sum('unique_views');
    }

    protected function getTodayViewsFromRecords(Model $viewable, string $collection): int
    {
        if (!config('viewable.analytics.include_today', true)) {
            return 0;
        }

        $today = $this->calendarManager->now()->startOfDay();

        return ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('viewed_at', '>=', $today)
            ->forBranch()
            ->count();
    }

    protected function getTodayUniqueViewsFromRecords(Model $viewable, string $collection): int
    {
        if (!config('viewable.analytics.include_today', true)) {
            return 0;
        }

        $today = $this->calendarManager->now()->startOfDay();

        return ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('viewed_at', '>=', $today)
            ->forBranch()
            ->distinct('visitor_key')
            ->count('visitor_key');
    }

    protected function getViewsForPeriod(
        Model $viewable,
        Period $period,
        string $collection,
        string $type,
    ): int {
        $column = $type === 'unique' ? 'unique_views' : 'total_views';

        // Get from aggregates for dates before today
        $aggregateCount = (int) ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forCalendar($period->getCalendar())
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->sum($column);

        // Get from records for today
        $today = $this->calendarManager->now()->startOfDay();
        $recordCount = 0;

        if (config('viewable.analytics.include_today', true) && $period->getEnd() >= $today) {
            $query = ViewableRecord::query()
                ->where('viewable_type', $viewable->getMorphClass())
                ->where('viewable_id', $viewable->getKey())
                ->where('collection', $collection)
                ->where('viewed_at', '>=', $today)
                ->where('viewed_at', '<=', $period->getEnd())
                ->forBranch();

            if ($type === 'unique') {
                $recordCount = $query->distinct('visitor_key')->count('visitor_key');
            } else {
                $recordCount = $query->count();
            }
        }

        return $aggregateCount + $recordCount;
    }
}
