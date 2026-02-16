<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Branch\Resolvers;

use Illuminate\Support\Facades\Auth;
use KarnoWeb\Viewable\Contracts\BranchResolverContract;

class DefaultBranchResolver implements BranchResolverContract
{
    /**
     * Resolve the current branch ID.
     *
     * This default implementation tries to get branch_id from:
     * 1. Authenticated user's branch_id attribute
     * 2. Request header 'X-Branch-ID'
     * 3. Request input 'branch_id'
     *
     * Override this class to implement your own resolution logic.
     */
    public function resolve(): ?int
    {
        // Try to get from authenticated user
        $user = Auth::user();
        if ($user && isset($user->branch_id)) {
            return (int) $user->branch_id;
        }

        // Try to get from request header
        $headerBranchId = request()->header('X-Branch-ID');
        if ($headerBranchId !== null) {
            return (int) $headerBranchId;
        }

        // Try to get from request input
        $inputBranchId = request()->input('branch_id');
        if ($inputBranchId !== null) {
            return (int) $inputBranchId;
        }

        return null;
    }
}
