<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use KarnoWeb\Viewable\Services\ViewableService;
use Symfony\Component\HttpFoundation\Response;

class RecordViewMiddleware
{
    public function __construct(
        protected ViewableService $viewableService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   Route::get('/posts/{post}', ...)->middleware('viewable:post');
     *   Route::get('/products/{product}', ...)->middleware('viewable:product,api');
     */
    public function handle(Request $request, Closure $next, string $parameter, ?string $collection = null): Response
    {
        $response = $next($request);

        // Only record on successful responses
        if ($response->isSuccessful()) {
            $viewable = $request->route($parameter);

            if ($viewable && is_object($viewable) && method_exists($viewable, 'recordView')) {
                $viewable->recordView($collection);
            }
        }

        return $response;
    }
}
