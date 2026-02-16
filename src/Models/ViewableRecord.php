<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ViewableRecord extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'viewed_at' => 'datetime',
        'user_id' => 'integer',
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
        $table = config('viewable.database.records_table', 'records');

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
     * Scope to filter by date range.
     */
    public function scopeBetween($query, $start, $end)
    {
        return $query->whereBetween('viewed_at', [$start, $end]);
    }
}
