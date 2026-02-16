<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use KarnoWeb\Viewable\DTOs\ViewData;
use KarnoWeb\Viewable\Services\ViewableService;

class RecordViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ViewData $viewData,
    ) {
        $this->onConnection(config('viewable.performance.queue.connection', 'default'));
        $this->onQueue(config('viewable.performance.queue.queue', 'default'));
    }

    public function handle(ViewableService $service): void
    {
        $service->processView($this->viewData);
    }
}
