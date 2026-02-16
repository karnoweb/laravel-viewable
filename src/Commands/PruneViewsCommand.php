<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Commands;

use Illuminate\Console\Command;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Models\ViewableRecord;

class PruneViewsCommand extends Command
{
    protected $signature = 'viewable:prune
                            {--days= : Delete records older than X days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Prune old raw view records';

    public function handle(CalendarManager $calendarManager): int
    {
        $days = (int) ($this->option('days') ?? config('viewable.compression.keep_raw_days', 1));
        $dryRun = $this->option('dry-run');

        $cutoff = $calendarManager->now()->subDays($days)->endOfDay();

        $query = ViewableRecord::query()->where('viewed_at', '<', $cutoff);
        $count = $query->count();

        $this->info("Found {$count} records older than {$days} days.");

        if ($dryRun) {
            $this->warn('Dry run - no records deleted.');
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No records to prune.');
            return self::SUCCESS;
        }

        if ($this->confirm("Delete {$count} records?")) {
            $deleted = $query->delete();
            $this->info("Deleted {$deleted} records.");
        }

        return self::SUCCESS;
    }
}
