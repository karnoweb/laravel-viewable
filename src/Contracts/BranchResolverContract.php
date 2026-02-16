<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Contracts;

interface BranchResolverContract
{
    /**
     * Resolve the current branch ID.
     * Returns null if no branch is active.
     */
    public function resolve(): ?int;
}
