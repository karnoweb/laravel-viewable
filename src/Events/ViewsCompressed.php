<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViewsCompressed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $date,
        public int $recordsProcessed,
        public int $recordsDeleted,
        public ?int $branchId = null,
    ) {}
}
