<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KarnoWeb\Viewable\DTOs\ViewData;

class ViewRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ViewData $viewData,
    ) {}
}
