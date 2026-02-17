<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use KarnoWeb\Viewable\Branch\BranchManager;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Events\ViewsCompressed;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;

class CompressDailyViewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $date = null,
        public ?int $branchId = null,
    ) {}

    public function handle(CalendarManager $calendarManager, BranchManager $branchManager): void
    {
        // Determine the date to compress (default: yesterday)
        $targetDate = $this->date
            ? $calendarManager->parse($this->date)
            : $calendarManager->now()->subDay();

        $startOfDay = $targetDate->copy()->startOfDay();
        $endOfDay = $targetDate->copy()->endOfDay();

        // Set branch context if provided
        if ($this->branchId !== null) {
            $branchManager->setBranchId($this->branchId);
        }

        $chunkSize = config('viewable.compression.chunk_size', 1000);
        $branchColumn = config('viewable.branch.column', 'branch_id');
        $branchEnabled = config('viewable.branch.enabled', false);

        // Get records table name
        $recordsTable = (new ViewableRecord)->getTable();

        // Build group by columns
        $groupByColumns = ['viewable_type', 'viewable_id', 'collection'];
        if ($branchEnabled) {
            $groupByColumns[] = $branchColumn;
        }

        // Query to get aggregated data using a subquery to avoid ONLY_FULL_GROUP_BY issues
        $subQuery = ViewableRecord::query()
            ->select($groupByColumns)
            ->selectRaw('COUNT(*) as total_views')
            ->selectRaw('COUNT(DISTINCT visitor_key) as unique_views')
            ->whereBetween('viewed_at', [$startOfDay, $endOfDay]);

        if ($branchEnabled && $this->branchId !== null) {
            $subQuery->where($branchColumn, $this->branchId);
        }

        $subQuery->groupBy($groupByColumns);

        // Use the subquery to fetch grouped results
        $query = DB::table(DB::raw("({$subQuery->toSql()}) as grouped_records"))
            ->mergeBindings($subQuery->getQuery()) // Merge bindings from the subquery
            ->select('*')
            ->orderBy('grouped_records.viewable_type'); // Add orderBy clause

        // Process in chunks
        $processedCount = 0;

        $query->chunk($chunkSize, function ($records) use (
            $startOfDay,
            $endOfDay,
            $calendarManager,
            $branchEnabled,
            $branchColumn,
            &$processedCount,
        ) {
            foreach ($records as $record) {
                // Create aggregates for each calendar type
                foreach (CalendarType::cases() as $calendarType) {
                    $adapter = $calendarManager->adapter($calendarType);

                    $periodKey = $adapter->getPeriodKey($startOfDay, 'daily');

                    $aggregateData = [
                        'viewable_type' => $record->viewable_type,
                        'viewable_id' => $record->viewable_id,
                        'collection' => $record->collection,
                        'calendar' => $calendarType->value,
                        'granularity' => Granularity::Daily->value,
                        'period_key' => $periodKey,
                        'period_start' => $startOfDay,
                        'period_end' => $endOfDay,
                    ];

                    if ($branchEnabled) {
                        $aggregateData[$branchColumn] = $record->{$branchColumn};
                    }

                    // Upsert the aggregate
                    ViewableAggregate::updateOrCreate(
                        collect($aggregateData)
                            ->except(['period_start', 'period_end'])
                            ->toArray(),
                        [
                            'total_views' => $record->total_views,
                            'unique_views' => $record->unique_views,
                            'period_start' => $startOfDay,
                            'period_end' => $endOfDay,
                        ]
                    );
                }

                $processedCount++;
            }
        });

        // Delete the raw records after successful compression
        $deleteQuery = ViewableRecord::query()
            ->whereBetween('viewed_at', [$startOfDay, $endOfDay]);

        if ($branchEnabled && $this->branchId !== null) {
            $deleteQuery->where($branchColumn, $this->branchId);
        }

        $deletedCount = $deleteQuery->delete();

        // Dispatch event
        event(new ViewsCompressed(
            date: $targetDate->toDateString(),
            recordsProcessed: $processedCount,
            recordsDeleted: $deletedCount,
            branchId: $this->branchId,
        ));
    }
}
