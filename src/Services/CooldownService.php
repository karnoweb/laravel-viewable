<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

// This service manages cooldown periods to prevent multiple views from the same visitor being recorded within a certain timeframe. It uses caching or session storage to track when a view was last recorded for a given visitor and model.
class CooldownService
{
    /**
     * Check if view should be recorded (not in cooldown).
     */
    public function canRecord(Model $viewable, string $visitorKey, string $collection): bool
    {
        if (!config('viewable.cooldown.enabled', true)) {
            return true;
        }

        $key = $this->getCacheKey($viewable, $visitorKey, $collection);

        return !$this->getStorage()->has($key);
    }

    /**
     * Mark that a view has been recorded (start cooldown).
     */
    public function markRecorded(Model $viewable, string $visitorKey, string $collection): void
    {
        if (!config('viewable.cooldown.enabled', true)) {
            return;
        }

        $key = $this->getCacheKey($viewable, $visitorKey, $collection);
        $minutes = $this->getCooldownMinutes($viewable);

        $this->getStorage()->put($key, true, now()->addMinutes($minutes));
    }

    /**
     * Get cooldown period in minutes for a model.
     */
    protected function getCooldownMinutes(Model $viewable): int
    {
        $modelClass = get_class($viewable);
        $perModel = config('viewable.cooldown.models', []);

        if (isset($perModel[$modelClass])) {
            return (int) $perModel[$modelClass];
        }

        return (int) config('viewable.cooldown.period', 60);
    }

    /**
     * Generate cache key for cooldown tracking.
     */
    protected function getCacheKey(Model $viewable, string $visitorKey, string $collection): string
    {
        $prefix = config('viewable.performance.cache.prefix', 'viewable:');

        return sprintf(
            '%scooldown:%s:%s:%s:%s',
            $prefix,
            $viewable->getMorphClass(),
            $viewable->getKey(),
            $collection,
            $visitorKey
        );
    }

    /**
     * Get the storage driver for cooldown tracking.
     */
    protected function getStorage()
    {
        $driver = config('viewable.cooldown.storage', 'cache');

        return match($driver) {
            'cache' => Cache::store(config('viewable.performance.cache.store')),
            'session' => session(),
            default => Cache::store(),
        };
    }
}
