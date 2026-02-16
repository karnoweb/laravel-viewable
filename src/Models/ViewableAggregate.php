<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;

class ViewableAggregate extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'total_views' => 'integer',
        'unique_views' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('viewable.database.connection'));
        $this->setTable($this->getConfiguredTable());
    }

    protected function getConfiguredTable(): string
    {
        $prefix = config('viewable.database.prefix', 'vw_');
        $table = config('viewable.database.aggregates_table', 'aggregates');

        return $prefix . $table;
    }

    /**
     * Get the viewable model.
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the calendar type enum.
     */
    public function getCalendarTypeAttribute(): CalendarType
    {
        return CalendarType::from($this->calendar);
    }

    /**
     * Get the granularity enum.
     */
    public function getGranularityTypeAttribute(): Granularity
    {
        return Granularity::from($this->granularity);
    }

    /**
     * Scope to filter by branch.
     */
    public function scopeForBranch($query, ?int $branchId = null)
    {
        if (!config('viewable.branch.enabled', false)) {
            return $query;
        }

        $column = config('viewable.branch.column', 'branch_id');

        if ($branchId === null) {
            $branchId = app(\KarnoWeb\Viewable\Branch\BranchManager::class)->getCurrentBranchId();
        }

        return $query->where($column, $branchId);
    }

    /**
     * Scope to filter by collection.
     */
    public function scopeForCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    /**
     * Scope to filter by calendar type.
     */
    public function scopeForCalendar($query, CalendarType|string $calendar)
    {
        $value = $calendar instanceof CalendarType ? $calendar->value : $calendar;
        return $query->where('calendar', $value);
    }

    /**
     * Scope to filter by granularity.
     */
    public function scopeForGranularity($query, Granularity|string $granularity)
    {
        $value = $granularity instanceof Granularity ? $granularity->value : $granularity;
        return $query->where('granularity', $value);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetween($query, $start, $end)
    {
        return $query->where('period_start', '>=', $start)
                     ->where('period_end', '<=', $end);
    }
}
