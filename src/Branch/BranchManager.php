<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Branch;

use KarnoWeb\Viewable\Contracts\BranchResolverContract;

class BranchManager
{
    protected ?BranchResolverContract $resolver = null;

    protected ?int $cachedBranchId = null;

    protected bool $resolved = false;

    /**
     * Check if branch feature is enabled.
     */
    public function isEnabled(): bool
    {
        return config('viewable.branch.enabled', false);
    }

    /**
     * Get the branch column name.
     */
    public function getColumn(): string
    {
        return config('viewable.branch.column', 'branch_id');
    }

    /**
     * Get the current branch ID.
     */
    public function getCurrentBranchId(): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if ($this->resolved) {
            return $this->cachedBranchId;
        }

        $this->cachedBranchId = $this->getResolver()->resolve();
        $this->resolved = true;

        return $this->cachedBranchId;
    }

    /**
     * Set the branch ID manually (useful for testing or background jobs).
     */
    public function setBranchId(?int $branchId): void
    {
        $this->cachedBranchId = $branchId;
        $this->resolved = true;
    }

    /**
     * Clear the cached branch ID.
     */
    public function clearCache(): void
    {
        $this->cachedBranchId = null;
        $this->resolved = false;
    }

    /**
     * Get the branch resolver instance.
     */
    protected function getResolver(): BranchResolverContract
    {
        if ($this->resolver !== null) {
            return $this->resolver;
        }

        $resolverClass = config('viewable.branch.resolver');

        $this->resolver = app($resolverClass);

        return $this->resolver;
    }

    /**
     * Set a custom resolver.
     */
    public function setResolver(BranchResolverContract $resolver): void
    {
        $this->resolver = $resolver;
        $this->clearCache();
    }
}
