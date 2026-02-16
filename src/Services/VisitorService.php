<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VisitorService
{
    /**
     * Get a unique visitor key based on configured identifiers.
     */
    public function getVisitorKey(): string
    {
        $identifiers = config('viewable.visitor.identifiers', ['user', 'session', 'ip']);
        $parts = [];

        foreach ($identifiers as $identifier) {
            $value = match($identifier) {
                'user' => $this->getUserId(),
                'session' => $this->getSessionId(),
                'ip' => $this->getIp(),
                default => null,
            };

            if ($value !== null) {
                $parts[] = $identifier . ':' . $value;
                break; // Use the first available identifier
            }
        }

        if (empty($parts)) {
            $parts[] = 'anonymous:' . Str::random(32);
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Get authenticated user ID.
     */
    public function getUserId(): ?int
    {
        // Try multiple guards
        foreach (['web', 'api', 'sanctum'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->id();
            }
        }

        return null;
    }

    /**
     * Get session ID.
     */
    public function getSessionId(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return session()->getId();
    }

    /**
     * Get visitor IP address.
     */
    public function getIp(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        $ip = request()->ip();

        if (config('viewable.visitor.hash_ip', false)) {
            return hash('sha256', $ip);
        }

        return $ip;
    }

    /**
     * Get user agent string.
     */
    public function getUserAgent(): ?string
    {
        if (!config('viewable.visitor.store_metadata.user_agent', false)) {
            return null;
        }

        if (app()->runningInConsole()) {
            return null;
        }

        return request()->userAgent();
    }

    /**
     * Get referer URL.
     */
    public function getReferer(): ?string
    {
        if (!config('viewable.visitor.store_metadata.referer', false)) {
            return null;
        }

        if (app()->runningInConsole()) {
            return null;
        }

        return request()->header('referer');
    }

    /**
     * Check if the current visitor is a bot.
     */
    public function isBot(): bool
    {
        if (!config('viewable.visitor.bot_detection.enabled', true)) {
            return false;
        }

        $userAgent = request()->userAgent() ?? '';

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'search', 'fetch',
            'facebook', 'twitter', 'linkedin', 'pinterest',
            'googlebot', 'bingbot', 'yandex', 'baidu',
            'curl', 'wget', 'python', 'php', 'java',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
