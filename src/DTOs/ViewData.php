<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use Illuminate\Database\Eloquent\Model;

final readonly class ViewData
{
    public function __construct(
        public Model $viewable,
        public string $collection,
        public string $visitorKey,
        public ?int $userId,
        public ?int $branchId,
        public ?string $ip,
        public ?string $userAgent,
        public ?string $referer,
    ) {}

    public static function fromRequest(Model $viewable, string $collection = 'default'): self
    {
        $visitorService = app(\KarnoWeb\Viewable\Services\VisitorService::class);
        $branchManager = app(\KarnoWeb\Viewable\Branch\BranchManager::class);

        return new self(
            viewable: $viewable,
            collection: $collection,
            visitorKey: $visitorService->getVisitorKey(),
            userId: $visitorService->getUserId(),
            branchId: $branchManager->getCurrentBranchId(),
            ip: $visitorService->getIp(),
            userAgent: $visitorService->getUserAgent(),
            referer: $visitorService->getReferer(),
        );
    }

    public function toArray(): array
    {
        $data = [
            'viewable_type' => $this->viewable->getMorphClass(),
            'viewable_id' => $this->viewable->getKey(),
            'collection' => $this->collection,
            'visitor_key' => $this->visitorKey,
            'user_id' => $this->userId,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
            'viewed_at' => now(),
        ];

        if (config('viewable.branch.enabled', false)) {
            $data[config('viewable.branch.column', 'branch_id')] = $this->branchId;
        }

        return $data;
    }
}
