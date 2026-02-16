<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use KarnoWeb\Viewable\Branch\BranchManager;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Commands\CompressViewsCommand;
use KarnoWeb\Viewable\Commands\PruneViewsCommand;
use KarnoWeb\Viewable\Contracts\BranchResolverContract;
use KarnoWeb\Viewable\Http\Middleware\RecordViewMiddleware;
use KarnoWeb\Viewable\Jobs\CompressDailyViewsJob;
use KarnoWeb\Viewable\Services\AnalyticsService;
use KarnoWeb\Viewable\Services\CooldownService;
use KarnoWeb\Viewable\Services\ViewableService;
use KarnoWeb\Viewable\Services\VisitorService;

class ViewableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/viewable.php', 'viewable');

        // Register services as singletons
        $this->app->singleton(CalendarManager::class);
        $this->app->singleton(BranchManager::class);
        $this->app->singleton(VisitorService::class);
        $this->app->singleton(CooldownService::class);

        $this->app->singleton(ViewableService::class, function ($app) {
            return new ViewableService(
                $app->make(VisitorService::class),
                $app->make(CooldownService::class),
                $app->make(BranchManager::class),
                $app->make(CalendarManager::class),
            );
        });

        $this->app->singleton(AnalyticsService::class, function ($app) {
            return new AnalyticsService(
                $app->make(CalendarManager::class),
                $app->make(BranchManager::class),
            );
        });

        // Register branch resolver
        $this->app->bind(BranchResolverContract::class, function ($app) {
            $resolverClass = config('viewable.branch.resolver');
            return $app->make($resolverClass);
        });

        // Register facade accessor
        $this->app->bind('viewable', function ($app) {
            return new class($app) {
                protected $app;

                public function __construct($app)
                {
                    $this->app = $app;
                }

                public function __call($method, $args)
                {
                    // Route to appropriate service
                    $analyticsService = $this->app->make(AnalyticsService::class);
                    $viewableService = $this->app->make(ViewableService::class);

                    if (method_exists($analyticsService, $method)) {
                        return $analyticsService->$method(...$args);
                    }

                    if (method_exists($viewableService, $method)) {
                        return $viewableService->$method(...$args);
                    }

                    throw new \BadMethodCallException("Method {$method} does not exist.");
                }
            };
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/viewable.php' => config_path('viewable.php'),
        ], 'viewable-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'viewable-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CompressViewsCommand::class,
                PruneViewsCommand::class,
            ]);
        }

        // Register middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('viewable', RecordViewMiddleware::class);

        // Schedule compression job
        $this->scheduleCompression();
    }

    protected function scheduleCompression(): void
    {
        if (!config('viewable.compression.enabled', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $cronExpression = config('viewable.compression.schedule', '0 1 * * *');

            $schedule->job(new CompressDailyViewsJob())
                     ->cron($cronExpression)
                     ->name('viewable:compress')
                     ->withoutOverlapping()
                     ->onOneServer();
        });
    }
}
