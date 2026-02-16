<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Commands;

use Illuminate\Console\Command;
use KarnoWeb\Viewable\Jobs\CompressDailyViewsJob;

class CompressViewsCommand extends Command
{
    protected $signature = 'viewable:compress
                            {--date= : The date to compress (default: yesterday)}
                            {--branch= : Specific branch ID to compress}
                            {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Compress raw view records into daily aggregates';

    public function handle(): int
    {
        $date = $this->option('date');
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $sync = $this->option('sync');

        $this->info("Compressing views for date: " . ($date ?? 'yesterday'));

        if ($branchId !== null) {
            $this->info("Branch ID: {$branchId}");
        }

        $job = new CompressDailyViewsJob($date, $branchId);

        if ($sync) {
            $job->handle(
                app(\KarnoWeb\Viewable\Calendar\CalendarManager::class),
                app(\KarnoWeb\Viewable\Branch\BranchManager::class)
            );
            $this->info('Compression completed synchronously.');
        } else {
            dispatch($job);
            $this->info('Compression job dispatched to queue.');
        }

        return self::SUCCESS;
    }
}
