# Laravel Viewable Package Implementation

## 📁 Project Structure

```
laravel-viewable/
├── composer.json
├── config/
│   └── viewable.php
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_viewable_records_table.php
│       └── 2024_01_01_000002_create_viewable_aggregates_table.php
├── src/
│   ├── ViewableServiceProvider.php
│   ├── Facades/
│   │   └── Viewable.php
│   ├── Contracts/
│   │   ├── ViewableContract.php
│   │   ├── CalendarAdapterContract.php
│   │   └── BranchResolverContract.php
│   ├── Models/
│   │   ├── ViewableRecord.php
│   │   └── ViewableAggregate.php
│   ├── Traits/
│   │   └── HasViews.php
│   ├── Services/
│   │   ├── ViewableService.php
│   │   ├── AnalyticsService.php
│   │   ├── VisitorService.php
│   │   └── CooldownService.php
│   ├── Calendar/
│   │   ├── CalendarManager.php
│   │   ├── Period.php
│   │   ├── Adapters/
│   │   │   ├── GregorianAdapter.php
│   │   │   └── JalaliAdapter.php
│   ├── Branch/
│   │   ├── BranchManager.php
│   │   └── Resolvers/
│   │       └── DefaultBranchResolver.php
│   ├── Jobs/
│   │   ├── CompressDailyViewsJob.php
│   │   └── RecordViewJob.php
│   ├── DTOs/
│   │   ├── ViewData.php
│   │   ├── AnalyticsResult.php
│   │   ├── GrowthData.php
│   │   ├── TimeSeriesPoint.php
│   │   └── PeriodData.php
│   ├── Enums/
│   │   ├── Granularity.php
│   │   ├── CalendarType.php
│   │   ├── Trend.php
│   │   └── RecordType.php
│   ├── Http/
│   │   └── Middleware/
│   │       └── RecordViewMiddleware.php
│   ├── Events/
│   │   ├── ViewRecorded.php
│   │   └── ViewsCompressed.php
│   └── Commands/
│       ├── CompressViewsCommand.php
│       └── PruneViewsCommand.php
└── tests/
```

---

## 1. composer.json

```json
{
    "name": "karnoweb/laravel-viewable",
    "description": "A powerful view tracking and analytics package for Laravel with Jalali/Gregorian calendar support",
    "keywords": ["laravel", "views", "analytics", "tracking", "jalali", "persian"],
    "license": "MIT",
    "authors": [
        {
            "name": "KarnoWeb",
            "email": "info@karnoweb.ir"
        }
    ],
    "require": {
        "php": "^8.1",
        "illuminate/support": "^10.0|^11.0",
        "illuminate/database": "^10.0|^11.0",
        "morilog/jalali": "^3.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0",
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "KarnoWeb\\Viewable\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "KarnoWeb\\Viewable\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "KarnoWeb\\Viewable\\ViewableServiceProvider"
            ],
            "aliases": {
                "Viewable": "KarnoWeb\\Viewable\\Facades\\Viewable"
            }
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

---

## 2. config/viewable.php

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database table names and prefix for the viewable package.
    |
    */
    'database' => [
        'connection' => env('VIEWABLE_DB_CONNECTION', null),
        'prefix' => env('VIEWABLE_DB_PREFIX', 'vw_'),
        'records_table' => 'records',
        'aggregates_table' => 'aggregates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Branch (Multi-tenant) Configuration
    |--------------------------------------------------------------------------
    |
    | Enable multi-branch support and configure how to resolve the current branch.
    |
    */
    'branch' => [
        'enabled' => env('VIEWABLE_BRANCH_ENABLED', false),
        'column' => 'branch_id',
        
        // The resolver class that implements BranchResolverContract
        // or a closure that returns the branch_id
        'resolver' => \KarnoWeb\Viewable\Branch\Resolvers\DefaultBranchResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendar Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default calendar system and timezone.
    |
    */
    'calendar' => [
        // 'gregorian' or 'jalali'
        'default' => env('VIEWABLE_CALENDAR', 'gregorian'),
        'timezone' => env('VIEWABLE_TIMEZONE', 'Asia/Tehran'),
        
        // Week starts on: 0 = Sunday, 6 = Saturday
        'week_starts_on' => 6,
        
        'jalali' => [
            'locale' => 'fa',
            'numbers' => 'latin', // 'latin' or 'persian'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection Configuration
    |--------------------------------------------------------------------------
    |
    | Define collections and guard mappings for categorizing views.
    |
    */
    'collections' => [
        'default' => 'default',
        'auto_detect' => true,
        
        'guards' => [
            'web' => 'web',
            'api' => 'api',
            'admin' => 'admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visitor Identification
    |--------------------------------------------------------------------------
    |
    | Configure how visitors are identified and tracked.
    |
    */
    'visitor' => [
        // Priority order for identifying unique visitors
        'identifiers' => ['user', 'session', 'ip'],
        
        // What metadata to store with each view
        'store_metadata' => [
            'ip' => true,
            'user_agent' => false,
            'referer' => false,
        ],
        
        // Hash the IP address for privacy
        'hash_ip' => false,
        
        // Bot detection
        'bot_detection' => [
            'enabled' => true,
            'ignore_bots' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cooldown Configuration
    |--------------------------------------------------------------------------
    |
    | Prevent counting multiple views from the same visitor in a short period.
    |
    */
    'cooldown' => [
        'enabled' => true,
        
        // Global cooldown in minutes
        'period' => 60,
        
        // Storage driver: 'cache', 'session', 'database'
        'storage' => 'cache',
        
        // Per-model cooldown (overrides global)
        // Example: App\Models\Post::class => 1440
        'models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance settings based on your traffic level.
    |
    */
    'performance' => [
        // Process view recording asynchronously
        'queue' => [
            'enabled' => env('VIEWABLE_QUEUE_ENABLED', false),
            'connection' => env('VIEWABLE_QUEUE_CONNECTION', 'default'),
            'queue' => env('VIEWABLE_QUEUE_NAME', 'default'),
        ],
        
        // Cache settings
        'cache' => [
            'enabled' => true,
            'ttl' => 3600, // seconds
            'prefix' => 'viewable:',
            'store' => env('VIEWABLE_CACHE_STORE', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how and when raw records are compressed into aggregates.
    |
    */
    'compression' => [
        'enabled' => true,
        
        // How many days to keep raw records before compression
        // After this period, records are compressed and deleted
        'keep_raw_days' => 1,
        
        // Schedule for the compression job (cron expression)
        'schedule' => '0 1 * * *', // 1:00 AM daily
        
        // Chunk size for processing records
        'chunk_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Counter Cache
    |--------------------------------------------------------------------------
    |
    | Automatically update a counter column on the viewable model.
    |
    */
    'counter_cache' => [
        'enabled' => true,
        
        // Column names to check and increment
        'columns' => ['view_count', 'views_count', 'hits'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure analytics and reporting features.
    |
    */
    'analytics' => [
        // Include current day data from raw records when generating reports
        'include_today' => true,
        
        // Default granularity for time series
        'default_granularity' => 'daily',
    ],

];
```

---

## 3. Migrations

### database/migrations/2024_01_01_000001_create_viewable_records_table.php

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('viewable.database.prefix', 'vw_');
        $table = $prefix . config('viewable.database.records_table', 'records');
        $connection = config('viewable.database.connection');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->id();
            
            // Branch support (multi-tenant)
            if (config('viewable.branch.enabled', false)) {
                $branchColumn = config('viewable.branch.column', 'branch_id');
                $table->unsignedBigInteger($branchColumn)->nullable()->index();
            }
            
            // Polymorphic relation to viewable model
            $table->string('viewable_type');
            $table->unsignedBigInteger('viewable_id');
            
            // Collection/category of view (web, api, admin, etc.)
            $table->string('collection', 50)->default('default');
            
            // Unique visitor identifier (hash of user_id/session_id/ip)
            $table->string('visitor_key', 64)->index();
            
            // Authenticated user (if available)
            $table->unsignedBigInteger('user_id')->nullable()->index();
            
            // Visitor metadata
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('referer', 500)->nullable();
            
            // Timestamp of the view
            $table->timestamp('viewed_at')->useCurrent()->index();
            
            // Composite indexes for common queries
            $table->index(['viewable_type', 'viewable_id', 'viewed_at']);
            $table->index(['viewable_type', 'viewable_id', 'collection']);
            $table->index(['viewable_type', 'viewable_id', 'visitor_key']);
        });
    }

    public function down(): void
    {
        $prefix = config('viewable.database.prefix', 'vw_');
        $table = $prefix . config('viewable.database.records_table', 'records');
        $connection = config('viewable.database.connection');

        Schema::connection($connection)->dropIfExists($table);
    }
};
```

### database/migrations/2024_01_01_000002_create_viewable_aggregates_table.php

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('viewable.database.prefix', 'vw_');
        $table = $prefix . config('viewable.database.aggregates_table', 'aggregates');
        $connection = config('viewable.database.connection');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->id();
            
            // Branch support (multi-tenant)
            if (config('viewable.branch.enabled', false)) {
                $branchColumn = config('viewable.branch.column', 'branch_id');
                $table->unsignedBigInteger($branchColumn)->nullable()->index();
            }
            
            // Polymorphic relation to viewable model
            $table->string('viewable_type');
            $table->unsignedBigInteger('viewable_id');
            
            // Collection/category
            $table->string('collection', 50)->default('default');
            
            // Calendar type used for this aggregate
            $table->string('calendar', 20)->default('gregorian');
            
            // Granularity: daily, weekly, monthly, yearly
            $table->string('granularity', 20)->default('daily');
            
            // Period identifier (e.g., "2024-01-15", "1402-10-25", "2024-01", "1402-10")
            $table->string('period_key', 20)->index();
            
            // Period boundaries
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            
            // Aggregated counts
            $table->unsignedInteger('total_views')->default(0);
            $table->unsignedInteger('unique_views')->default(0);
            
            // Optional: breakdown data as JSON
            // Can store hourly distribution, top referrers, etc.
            $table->json('metadata')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            // Unique constraint to prevent duplicate aggregates
            $uniqueColumns = ['viewable_type', 'viewable_id', 'collection', 'calendar', 'granularity', 'period_key'];
            if (config('viewable.branch.enabled', false)) {
                array_unshift($uniqueColumns, config('viewable.branch.column', 'branch_id'));
            }
            $table->unique($uniqueColumns, 'viewable_aggregates_unique');
            
            // Composite indexes for queries
            $table->index(['viewable_type', 'viewable_id', 'granularity', 'period_start']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        $prefix = config('viewable.database.prefix', 'vw_');
        $table = $prefix . config('viewable.database.aggregates_table', 'aggregates');
        $connection = config('viewable.database.connection');

        Schema::connection($connection)->dropIfExists($table);
    }
};
```

---

## 4. Enums

### src/Enums/CalendarType.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Enums;

enum CalendarType: string
{
    case Gregorian = 'gregorian';
    case Jalali = 'jalali';
    
    public function label(): string
    {
        return match($this) {
            self::Gregorian => 'Gregorian',
            self::Jalali => 'Jalali (Shamsi)',
        };
    }
}
```

### src/Enums/Granularity.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Enums;

enum Granularity: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    
    public function label(): string
    {
        return match($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
```

### src/Enums/Trend.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Enums;

enum Trend: string
{
    case Up = 'up';
    case Down = 'down';
    case Stable = 'stable';
    
    public function label(): string
    {
        return match($this) {
            self::Up => 'Growing',
            self::Down => 'Declining',
            self::Stable => 'Stable',
        };
    }
    
    public function icon(): string
    {
        return match($this) {
            self::Up => '↑',
            self::Down => '↓',
            self::Stable => '→',
        };
    }
}
```

---

## 5. Contracts

### src/Contracts/ViewableContract.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;

interface ViewableContract
{
    /**
     * Get all view records for this model.
     */
    public function viewRecords(): MorphMany;
    
    /**
     * Get all aggregated views for this model.
     */
    public function viewAggregates(): MorphMany;
    
    /**
     * Record a view for this model.
     */
    public function recordView(?string $collection = null): bool;
    
    /**
     * Get total views count.
     */
    public function viewsCount(?Period $period = null, ?string $collection = null): int;
    
    /**
     * Get unique views count.
     */
    public function uniqueViewsCount(?Period $period = null, ?string $collection = null): int;
    
    /**
     * Get analytics for this model.
     */
    public function analytics(): AnalyticsResult;
}
```

### src/Contracts/CalendarAdapterContract.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Contracts;

use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Enums\CalendarType;

interface CalendarAdapterContract
{
    /**
     * Get the calendar type.
     */
    public function getType(): CalendarType;
    
    /**
     * Format a date according to this calendar.
     */
    public function format(CarbonInterface $date, string $format): string;
    
    /**
     * Get the start of a day.
     */
    public function startOfDay(CarbonInterface $date): CarbonInterface;
    
    /**
     * Get the end of a day.
     */
    public function endOfDay(CarbonInterface $date): CarbonInterface;
    
    /**
     * Get the start of a week.
     */
    public function startOfWeek(CarbonInterface $date): CarbonInterface;
    
    /**
     * Get the end of a week.
     */
    public function endOfWeek(CarbonInterface $date): CarbonInterface;
    
    /**
     * Get the start of a month.
     */
    public function startOfMonth(CarbonInterface $date): CarbonInterface;
    
    /**
     * Get the end of a month.
     */
    public function endOfMonth(CarbonInterface $date): CarbonInterface;
    
    /**
     * Get the start of a year.
     */
    public function startOfYear(CarbonInterface $date): CarbonInterface;
    
    /**
     * Get the end of a year.
     */
    public function endOfYear(CarbonInterface $date): CarbonInterface;
    
    /**
     * Create a date from year, month, day.
     */
    public function createDate(int $year, int $month, int $day): CarbonInterface;
    
    /**
     * Get period key for a date (e.g., "2024-01-15" or "1402-10-25").
     */
    public function getPeriodKey(CarbonInterface $date, string $granularity): string;
    
    /**
     * Get human-readable label for a period.
     */
    public function getPeriodLabel(CarbonInterface $date, string $granularity): string;
}
```

### src/Contracts/BranchResolverContract.php

```php
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
```

---

## 6. DTOs

### src/DTOs/ViewData.php

```php
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
```

### src/DTOs/PeriodData.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;

final readonly class PeriodData
{
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
        public Granularity $granularity,
        public CalendarType $calendar,
        public string $label,
        public string $key,
        public int $days,
    ) {}
    
    public function toArray(): array
    {
        return [
            'start' => $this->start->toDateTimeString(),
            'end' => $this->end->toDateTimeString(),
            'granularity' => $this->granularity->value,
            'calendar' => $this->calendar->value,
            'label' => $this->label,
            'key' => $this->key,
            'days' => $this->days,
        ];
    }
}
```

### src/DTOs/TimeSeriesPoint.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use Carbon\CarbonInterface;

final readonly class TimeSeriesPoint
{
    public function __construct(
        public CarbonInterface $date,
        public string $label,
        public string $key,
        public int $total,
        public int $unique,
        public float $growthPercentage,
    ) {}
    
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'label' => $this->label,
            'key' => $this->key,
            'total' => $this->total,
            'unique' => $this->unique,
            'growth_percentage' => round($this->growthPercentage, 2),
        ];
    }
}
```

### src/DTOs/GrowthData.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use KarnoWeb\Viewable\Enums\Trend;

final readonly class GrowthData
{
    public function __construct(
        public float $percentage,
        public int $absolute,
        public Trend $trend,
        public int $currentValue,
        public int $previousValue,
    ) {}
    
    public static function calculate(int $current, int $previous): self
    {
        $absolute = $current - $previous;
        
        if ($previous === 0) {
            $percentage = $current > 0 ? 100.0 : 0.0;
        } else {
            $percentage = (($current - $previous) / $previous) * 100;
        }
        
        $trend = match(true) {
            $percentage > 1 => Trend::Up,
            $percentage < -1 => Trend::Down,
            default => Trend::Stable,
        };
        
        return new self(
            percentage: round($percentage, 2),
            absolute: $absolute,
            trend: $trend,
            currentValue: $current,
            previousValue: $previous,
        );
    }
    
    public function toArray(): array
    {
        return [
            'percentage' => $this->percentage,
            'absolute' => $this->absolute,
            'trend' => $this->trend->value,
            'trend_label' => $this->trend->label(),
            'trend_icon' => $this->trend->icon(),
            'current_value' => $this->currentValue,
            'previous_value' => $this->previousValue,
        ];
    }
}
```

### src/DTOs/AnalyticsResult.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\DTOs;

use Illuminate\Support\Collection;

final readonly class AnalyticsResult
{
    /**
     * @param Collection<int, TimeSeriesPoint> $timeSeries
     */
    public function __construct(
        public PeriodData $period,
        public int $totalViews,
        public int $uniqueViews,
        public GrowthData $growth,
        public Collection $timeSeries,
        public TimeSeriesPoint $peak,
        public TimeSeriesPoint $lowest,
        public float $averageDaily,
    ) {}
    
    public function toArray(): array
    {
        return [
            'period' => $this->period->toArray(),
            'total_views' => $this->totalViews,
            'unique_views' => $this->uniqueViews,
            'growth' => $this->growth->toArray(),
            'time_series' => $this->timeSeries->map->toArray()->values()->all(),
            'peak' => $this->peak->toArray(),
            'lowest' => $this->lowest->toArray(),
            'average_daily' => round($this->averageDaily, 2),
        ];
    }
    
    /**
     * Get data formatted for chart libraries.
     */
    public function forChart(): array
    {
        return [
            'labels' => $this->timeSeries->pluck('label')->all(),
            'datasets' => [
                [
                    'name' => 'Total Views',
                    'data' => $this->timeSeries->pluck('total')->all(),
                ],
                [
                    'name' => 'Unique Views',
                    'data' => $this->timeSeries->pluck('unique')->all(),
                ],
            ],
        ];
    }
}
```

---

## 7. Models

### src/Models/ViewableRecord.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ViewableRecord extends Model
{
    public $timestamps = false;
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'viewed_at' => 'datetime',
        'user_id' => 'integer',
    ];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->setConnection(config('viewable.database.connection'));
        $this->setTable($this->getConfiguredTable());
    }
    
    protected function getConfiguredTable(): string
    {
        $prefix = config('viewable.database.prefix', 'vw_');
        $table = config('viewable.database.records_table', 'records');
        
        return $prefix . $table;
    }
    
    /**
     * Get the viewable model.
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
    
    /**
     * Scope to filter by branch.
     */
    public function scopeForBranch($query, ?int $branchId = null)
    {
        if (!config('viewable.branch.enabled', false)) {
            return $query;
        }
        
        $column = config('viewable.branch.column', 'branch_id');
        
        if ($branchId === null) {
            $branchId = app(\KarnoWeb\Viewable\Branch\BranchManager::class)->getCurrentBranchId();
        }
        
        return $query->where($column, $branchId);
    }
    
    /**
     * Scope to filter by collection.
     */
    public function scopeForCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }
    
    /**
     * Scope to filter by date range.
     */
    public function scopeBetween($query, $start, $end)
    {
        return $query->whereBetween('viewed_at', [$start, $end]);
    }
}
```

### src/Models/ViewableAggregate.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;

class ViewableAggregate extends Model
{
    public $timestamps = false;
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'total_views' => 'integer',
        'unique_views' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->setConnection(config('viewable.database.connection'));
        $this->setTable($this->getConfiguredTable());
    }
    
    protected function getConfiguredTable(): string
    {
        $prefix = config('viewable.database.prefix', 'vw_');
        $table = config('viewable.database.aggregates_table', 'aggregates');
        
        return $prefix . $table;
    }
    
    /**
     * Get the viewable model.
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
    
    /**
     * Get the calendar type enum.
     */
    public function getCalendarTypeAttribute(): CalendarType
    {
        return CalendarType::from($this->calendar);
    }
    
    /**
     * Get the granularity enum.
     */
    public function getGranularityTypeAttribute(): Granularity
    {
        return Granularity::from($this->granularity);
    }
    
    /**
     * Scope to filter by branch.
     */
    public function scopeForBranch($query, ?int $branchId = null)
    {
        if (!config('viewable.branch.enabled', false)) {
            return $query;
        }
        
        $column = config('viewable.branch.column', 'branch_id');
        
        if ($branchId === null) {
            $branchId = app(\KarnoWeb\Viewable\Branch\BranchManager::class)->getCurrentBranchId();
        }
        
        return $query->where($column, $branchId);
    }
    
    /**
     * Scope to filter by collection.
     */
    public function scopeForCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }
    
    /**
     * Scope to filter by calendar type.
     */
    public function scopeForCalendar($query, CalendarType|string $calendar)
    {
        $value = $calendar instanceof CalendarType ? $calendar->value : $calendar;
        return $query->where('calendar', $value);
    }
    
    /**
     * Scope to filter by granularity.
     */
    public function scopeForGranularity($query, Granularity|string $granularity)
    {
        $value = $granularity instanceof Granularity ? $granularity->value : $granularity;
        return $query->where('granularity', $value);
    }
    
    /**
     * Scope to filter by date range.
     */
    public function scopeBetween($query, $start, $end)
    {
        return $query->where('period_start', '>=', $start)
                     ->where('period_end', '<=', $end);
    }
}
```

---

## 8. Branch Management

### src/Branch/BranchManager.php

```php
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
```

### src/Branch/Resolvers/DefaultBranchResolver.php

```php
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
```

---

## 9. Calendar System

### src/Calendar/CalendarManager.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Calendar\Adapters\GregorianAdapter;
use KarnoWeb\Viewable\Calendar\Adapters\JalaliAdapter;
use KarnoWeb\Viewable\Contracts\CalendarAdapterContract;
use KarnoWeb\Viewable\Enums\CalendarType;

class CalendarManager
{
    protected array $adapters = [];
    
    /**
     * Get the default calendar type.
     */
    public function getDefaultType(): CalendarType
    {
        return CalendarType::from(config('viewable.calendar.default', 'gregorian'));
    }
    
    /**
     * Get the configured timezone.
     */
    public function getTimezone(): string
    {
        return config('viewable.calendar.timezone', 'UTC');
    }
    
    /**
     * Get an adapter for the specified calendar type.
     */
    public function adapter(CalendarType|string|null $type = null): CalendarAdapterContract
    {
        if ($type === null) {
            $type = $this->getDefaultType();
        }
        
        if (is_string($type)) {
            $type = CalendarType::from($type);
        }
        
        $key = $type->value;
        
        if (!isset($this->adapters[$key])) {
            $this->adapters[$key] = $this->createAdapter($type);
        }
        
        return $this->adapters[$key];
    }
    
    /**
     * Create an adapter instance.
     */
    protected function createAdapter(CalendarType $type): CalendarAdapterContract
    {
        return match($type) {
            CalendarType::Gregorian => new GregorianAdapter($this->getTimezone()),
            CalendarType::Jalali => new JalaliAdapter($this->getTimezone()),
        };
    }
    
    /**
     * Get current date in the configured timezone.
     */
    public function now(): CarbonInterface
    {
        return Carbon::now($this->getTimezone());
    }
    
    /**
     * Parse a date string.
     */
    public function parse(string $date): CarbonInterface
    {
        return Carbon::parse($date, $this->getTimezone());
    }
}
```

### src/Calendar/Period.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\DTOs\PeriodData;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;

class Period
{
    protected CarbonInterface $start;
    protected CarbonInterface $end;
    protected CalendarType $calendar;
    protected Granularity $granularity;
    
    public function __construct(
        CarbonInterface $start,
        CarbonInterface $end,
        CalendarType $calendar = CalendarType::Gregorian,
        Granularity $granularity = Granularity::Daily,
    ) {
        $this->start = $start;
        $this->end = $end;
        $this->calendar = $calendar;
        $this->granularity = $granularity;
    }
    
    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------
    
    public function getStart(): CarbonInterface
    {
        return $this->start;
    }
    
    public function getEnd(): CarbonInterface
    {
        return $this->end;
    }
    
    public function getCalendar(): CalendarType
    {
        return $this->calendar;
    }
    
    public function getGranularity(): Granularity
    {
        return $this->granularity;
    }
    
    public function getDays(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }
    
    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------
    
    public function calendar(CalendarType $calendar): self
    {
        $this->calendar = $calendar;
        return $this;
    }
    
    public function granularity(Granularity $granularity): self
    {
        $this->granularity = $granularity;
        return $this;
    }
    
    public function asJalali(): self
    {
        $this->calendar = CalendarType::Jalali;
        return $this;
    }
    
    public function asGregorian(): self
    {
        $this->calendar = CalendarType::Gregorian;
        return $this;
    }
    
    // -------------------------------------------------------------------------
    // Static constructors - Gregorian
    // -------------------------------------------------------------------------
    
    public static function today(): self
    {
        $now = self::now();
        return new self($now->copy()->startOfDay(), $now->copy()->endOfDay());
    }
    
    public static function yesterday(): self
    {
        $yesterday = self::now()->subDay();
        return new self($yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay());
    }
    
    public static function thisWeek(): self
    {
        $now = self::now();
        return new self(
            $now->copy()->startOfWeek(config('viewable.calendar.week_starts_on', 6)),
            $now->copy()->endOfWeek(config('viewable.calendar.week_starts_on', 6)),
            granularity: Granularity::Daily,
        );
    }
    
    public static function lastWeek(): self
    {
        $now = self::now()->subWeek();
        return new self(
            $now->copy()->startOfWeek(config('viewable.calendar.week_starts_on', 6)),
            $now->copy()->endOfWeek(config('viewable.calendar.week_starts_on', 6)),
            granularity: Granularity::Daily,
        );
    }
    
    public static function thisMonth(): self
    {
        $now = self::now();
        return new self(
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
            granularity: Granularity::Daily,
        );
    }
    
    public static function lastMonth(): self
    {
        $now = self::now()->subMonth();
        return new self(
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
            granularity: Granularity::Daily,
        );
    }
    
    public static function thisYear(): self
    {
        $now = self::now();
        return new self(
            $now->copy()->startOfYear(),
            $now->copy()->endOfYear(),
            granularity: Granularity::Monthly,
        );
    }
    
    public static function lastDays(int $days): self
    {
        $now = self::now();
        return new self(
            $now->copy()->subDays($days - 1)->startOfDay(),
            $now->copy()->endOfDay(),
            granularity: Granularity::Daily,
        );
    }
    
    public static function lastHours(int $hours): self
    {
        $now = self::now();
        return new self(
            $now->copy()->subHours($hours),
            $now,
            granularity: Granularity::Hourly,
        );
    }
    
    public static function between(CarbonInterface|string $start, CarbonInterface|string $end): self
    {
        $manager = app(CalendarManager::class);
        
        $start = is_string($start) ? $manager->parse($start) : $start;
        $end = is_string($end) ? $manager->parse($end) : $end;
        
        return new self($start, $end);
    }
    
    // -------------------------------------------------------------------------
    // Static constructors - Jalali
    // -------------------------------------------------------------------------
    
    public static function jalaliToday(): self
    {
        return self::today()->asJalali();
    }
    
    public static function jalaliThisWeek(): self
    {
        return self::thisWeek()->asJalali();
    }
    
    public static function jalaliThisMonth(): self
    {
        return self::thisMonth()->asJalali();
    }
    
    public static function jalaliMonth(int $year, int $month): self
    {
        $adapter = app(CalendarManager::class)->adapter(CalendarType::Jalali);
        $start = $adapter->createDate($year, $month, 1);
        $end = $adapter->endOfMonth($start);
        
        return new self($start, $end, CalendarType::Jalali, Granularity::Daily);
    }
    
    public static function jalaliYear(int $year): self
    {
        $adapter = app(CalendarManager::class)->adapter(CalendarType::Jalali);
        $start = $adapter->createDate($year, 1, 1);
        $end = $adapter->endOfYear($start);
        
        return new self($start, $end, CalendarType::Jalali, Granularity::Monthly);
    }
    
    // -------------------------------------------------------------------------
    // Previous period (for comparison)
    // -------------------------------------------------------------------------
    
    public function previousPeriod(): self
    {
        $days = $this->getDays();
        
        return new self(
            $this->start->copy()->subDays($days),
            $this->start->copy()->subDay()->endOfDay(),
            $this->calendar,
            $this->granularity,
        );
    }
    
    // -------------------------------------------------------------------------
    // Conversion to DTO
    // -------------------------------------------------------------------------
    
    public function toData(): PeriodData
    {
        $adapter = app(CalendarManager::class)->adapter($this->calendar);
        
        return new PeriodData(
            start: $this->start,
            end: $this->end,
            granularity: $this->granularity,
            calendar: $this->calendar,
            label: $adapter->getPeriodLabel($this->start, $this->granularity->value),
            key: $adapter->getPeriodKey($this->start, $this->granularity->value),
            days: $this->getDays(),
        );
    }
    
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    
    protected static function now(): CarbonInterface
    {
        return app(CalendarManager::class)->now();
    }
}
```

### src/Calendar/Adapters/GregorianAdapter.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar\Adapters;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Contracts\CalendarAdapterContract;
use KarnoWeb\Viewable\Enums\CalendarType;

class GregorianAdapter implements CalendarAdapterContract
{
    public function __construct(
        protected string $timezone = 'UTC',
    ) {}
    
    public function getType(): CalendarType
    {
        return CalendarType::Gregorian;
    }
    
    public function format(CarbonInterface $date, string $format): string
    {
        return $date->format($format);
    }
    
    public function startOfDay(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfDay();
    }
    
    public function endOfDay(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfDay();
    }
    
    public function startOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfWeek(config('viewable.calendar.week_starts_on', 0));
    }
    
    public function endOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfWeek(config('viewable.calendar.week_starts_on', 0));
    }
    
    public function startOfMonth(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfMonth();
    }
    
    public function endOfMonth(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfMonth();
    }
    
    public function startOfYear(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfYear();
    }
    
    public function endOfYear(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfYear();
    }
    
    public function createDate(int $year, int $month, int $day): CarbonInterface
    {
        return Carbon::create($year, $month, $day, 0, 0, 0, $this->timezone);
    }
    
    public function getPeriodKey(CarbonInterface $date, string $granularity): string
    {
        return match($granularity) {
            'hourly' => $date->format('Y-m-d-H'),
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->format('Y-W'),
            'monthly' => $date->format('Y-m'),
            'yearly' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }
    
    public function getPeriodLabel(CarbonInterface $date, string $granularity): string
    {
        return match($granularity) {
            'hourly' => $date->format('M d, H:00'),
            'daily' => $date->format('M d, Y'),
            'weekly' => 'Week ' . $date->format('W, Y'),
            'monthly' => $date->format('F Y'),
            'yearly' => $date->format('Y'),
            default => $date->format('M d, Y'),
        };
    }
}
```

### src/Calendar/Adapters/JalaliAdapter.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Calendar\Adapters;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use KarnoWeb\Viewable\Contracts\CalendarAdapterContract;
use KarnoWeb\Viewable\Enums\CalendarType;
use Morilog\Jalali\Jalalian;

class JalaliAdapter implements CalendarAdapterContract
{
    protected array $monthNames = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];
    
    public function __construct(
        protected string $timezone = 'Asia/Tehran',
    ) {}
    
    public function getType(): CalendarType
    {
        return CalendarType::Jalali;
    }
    
    public function format(CarbonInterface $date, string $format): string
    {
        return Jalalian::fromCarbon($date)->format($format);
    }
    
    public function startOfDay(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->startOfDay();
    }
    
    public function endOfDay(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfDay();
    }
    
    public function startOfWeek(CarbonInterface $date): CarbonInterface
    {
        // In Jalali calendar, week starts on Saturday (6)
        return $date->copy()->startOfWeek(Carbon::SATURDAY);
    }
    
    public function endOfWeek(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->endOfWeek(Carbon::SATURDAY);
    }
    
    public function startOfMonth(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-%02d-01', $jalali->getYear(), $jalali->getMonth()))
            ->toCarbon()
            ->startOfDay();
    }
    
    public function endOfMonth(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        $daysInMonth = $jalali->getMonthDays();
        
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $daysInMonth))
            ->toCarbon()
            ->endOfDay();
    }
    
    public function startOfYear(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-01-01', $jalali->getYear()))
            ->toCarbon()
            ->startOfDay();
    }
    
    public function endOfYear(CarbonInterface $date): CarbonInterface
    {
        $jalali = Jalalian::fromCarbon($date);
        $isLeap = $jalali->isLeapYear();
        $lastDay = $isLeap ? 30 : 29;
        
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-12-%02d', $jalali->getYear(), $lastDay))
            ->toCarbon()
            ->endOfDay();
    }
    
    public function createDate(int $year, int $month, int $day): CarbonInterface
    {
        return Jalalian::fromFormat('Y-m-d', sprintf('%d-%02d-%02d', $year, $month, $day))
            ->toCarbon($this->timezone);
    }
    
    public function getPeriodKey(CarbonInterface $date, string $granularity): string
    {
        $jalali = Jalalian::fromCarbon($date);
        
        return match($granularity) {
            'hourly' => sprintf('%d-%02d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $jalali->getDay(), $date->hour),
            'daily' => sprintf('%d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $jalali->getDay()),
            'weekly' => sprintf('%d-W%02d', $jalali->getYear(), $jalali->getWeekOfYear()),
            'monthly' => sprintf('%d-%02d', $jalali->getYear(), $jalali->getMonth()),
            'yearly' => (string) $jalali->getYear(),
            default => sprintf('%d-%02d-%02d', $jalali->getYear(), $jalali->getMonth(), $jalali->getDay()),
        };
    }
    
    public function getPeriodLabel(CarbonInterface $date, string $granularity): string
    {
        $jalali = Jalalian::fromCarbon($date);
        $monthName = $this->monthNames[$jalali->getMonth()];
        
        return match($granularity) {
            'hourly' => sprintf('%d %s، ساعت %02d', $jalali->getDay(), $monthName, $date->hour),
            'daily' => sprintf('%d %s %d', $jalali->getDay(), $monthName, $jalali->getYear()),
            'weekly' => sprintf('هفته %d، %d', $jalali->getWeekOfYear(), $jalali->getYear()),
            'monthly' => sprintf('%s %d', $monthName, $jalali->getYear()),
            'yearly' => (string) $jalali->getYear(),
            default => sprintf('%d %s %d', $jalali->getDay(), $monthName, $jalali->getYear()),
        };
    }
}
```

---

## 10. Services

### src/Services/VisitorService.php

```php
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
```

### src/Services/CooldownService.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
```

### src/Services/ViewableService.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use KarnoWeb\Viewable\Branch\BranchManager;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\ViewData;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Events\ViewRecorded;
use KarnoWeb\Viewable\Jobs\RecordViewJob;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;

class ViewableService
{
    public function __construct(
        protected VisitorService $visitorService,
        protected CooldownService $cooldownService,
        protected BranchManager $branchManager,
        protected CalendarManager $calendarManager,
    ) {}
    
    /**
     * Record a view for a model.
     */
    public function record(Model $viewable, ?string $collection = null): bool
    {
        // Check if bot should be ignored
        if (config('viewable.visitor.bot_detection.ignore_bots', true) && $this->visitorService->isBot()) {
            return false;
        }
        
        $collection = $collection ?? $this->detectCollection();
        $visitorKey = $this->visitorService->getVisitorKey();
        
        // Check cooldown
        if (!$this->cooldownService->canRecord($viewable, $visitorKey, $collection)) {
            return false;
        }
        
        $viewData = ViewData::fromRequest($viewable, $collection);
        
        // Process synchronously or via queue
        if (config('viewable.performance.queue.enabled', false)) {
            RecordViewJob::dispatch($viewData);
        } else {
            $this->processView($viewData);
        }
        
        // Mark cooldown
        $this->cooldownService->markRecorded($viewable, $visitorKey, $collection);
        
        return true;
    }
    
    /**
     * Process and store the view.
     */
    public function processView(ViewData $viewData): void
    {
        // Store the raw record
        ViewableRecord::create($viewData->toArray());
        
        // Update counter cache on the model if configured
        if (config('viewable.counter_cache.enabled', true)) {
            $this->updateCounterCache($viewData->viewable);
        }
        
        // Dispatch event
        event(new ViewRecorded($viewData));
    }
    
    /**
     * Get total views count for a model.
     */
    public function getViewsCount(
        Model $viewable,
        ?Period $period = null,
        ?string $collection = null,
    ): int {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        
        if ($period === null) {
            return $this->getTotalViewsFromAggregates($viewable, $collection)
                 + $this->getTodayViewsFromRecords($viewable, $collection);
        }
        
        return $this->getViewsForPeriod($viewable, $period, $collection, 'total');
    }
    
    /**
     * Get unique views count for a model.
     */
    public function getUniqueViewsCount(
        Model $viewable,
        ?Period $period = null,
        ?string $collection = null,
    ): int {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        
        if ($period === null) {
            return $this->getTotalUniqueViewsFromAggregates($viewable, $collection)
                 + $this->getTodayUniqueViewsFromRecords($viewable, $collection);
        }
        
        return $this->getViewsForPeriod($viewable, $period, $collection, 'unique');
    }
    
    /**
     * Check if a visitor has already viewed the model.
     */
    public function hasViewed(Model $viewable, ?string $collection = null): bool
    {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        $visitorKey = $this->visitorService->getVisitorKey();
        
        return ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('visitor_key', $visitorKey)
            ->forBranch()
            ->exists();
    }
    
    // -------------------------------------------------------------------------
    // Protected methods
    // -------------------------------------------------------------------------
    
    protected function detectCollection(): string
    {
        if (!config('viewable.collections.auto_detect', true)) {
            return config('viewable.collections.default', 'default');
        }
        
        $guards = config('viewable.collections.guards', []);
        
        foreach ($guards as $guard => $collection) {
            if (auth()->guard($guard)->check()) {
                return $collection;
            }
        }
        
        // Detect from request
        if (request()->expectsJson() || request()->is('api/*')) {
            return 'api';
        }
        
        return 'web';
    }
    
    protected function updateCounterCache(Model $viewable): void
    {
        $columns = config('viewable.counter_cache.columns', ['view_count']);
        $attributes = $viewable->getAttributes();
        
        foreach ($columns as $column) {
            if (array_key_exists($column, $attributes)) {
                $viewable->increment($column);
                break;
            }
        }
    }
    
    protected function getTotalViewsFromAggregates(Model $viewable, string $collection): int
    {
        return (int) ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forBranch()
            ->sum('total_views');
    }
    
    protected function getTotalUniqueViewsFromAggregates(Model $viewable, string $collection): int
    {
        return (int) ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forBranch()
            ->sum('unique_views');
    }
    
    protected function getTodayViewsFromRecords(Model $viewable, string $collection): int
    {
        if (!config('viewable.analytics.include_today', true)) {
            return 0;
        }
        
        $today = $this->calendarManager->now()->startOfDay();
        
        return ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('viewed_at', '>=', $today)
            ->forBranch()
            ->count();
    }
    
    protected function getTodayUniqueViewsFromRecords(Model $viewable, string $collection): int
    {
        if (!config('viewable.analytics.include_today', true)) {
            return 0;
        }
        
        $today = $this->calendarManager->now()->startOfDay();
        
        return ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('viewed_at', '>=', $today)
            ->forBranch()
            ->distinct('visitor_key')
            ->count('visitor_key');
    }
    
    protected function getViewsForPeriod(
        Model $viewable,
        Period $period,
        string $collection,
        string $type,
    ): int {
        $column = $type === 'unique' ? 'unique_views' : 'total_views';
        
        // Get from aggregates for dates before today
        $aggregateCount = (int) ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forCalendar($period->getCalendar())
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->sum($column);
        
        // Get from records for today
        $today = $this->calendarManager->now()->startOfDay();
        $recordCount = 0;
        
        if (config('viewable.analytics.include_today', true) && $period->getEnd() >= $today) {
            $query = ViewableRecord::query()
                ->where('viewable_type', $viewable->getMorphClass())
                ->where('viewable_id', $viewable->getKey())
                ->where('collection', $collection)
                ->where('viewed_at', '>=', $today)
                ->where('viewed_at', '<=', $period->getEnd())
                ->forBranch();
            
            if ($type === 'unique') {
                $recordCount = $query->distinct('visitor_key')->count('visitor_key');
            } else {
                $recordCount = $query->count();
            }
        }
        
        return $aggregateCount + $recordCount;
    }
}
```

### src/Services/AnalyticsService.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use KarnoWeb\Viewable\Branch\BranchManager;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;
use KarnoWeb\Viewable\DTOs\GrowthData;
use KarnoWeb\Viewable\DTOs\TimeSeriesPoint;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;

class AnalyticsService
{
    public function __construct(
        protected CalendarManager $calendarManager,
        protected BranchManager $branchManager,
    ) {}
    
    /**
     * Get analytics for a viewable model.
     */
    public function getAnalytics(
        Model $viewable,
        Period $period,
        ?string $collection = null,
    ): AnalyticsResult {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        
        // Get time series data
        $timeSeries = $this->getTimeSeries($viewable, $period, $collection);
        
        // Calculate totals
        $totalViews = $timeSeries->sum('total');
        $uniqueViews = $timeSeries->sum('unique');
        
        // Get previous period for comparison
        $previousPeriod = $period->previousPeriod();
        $previousTimeSeries = $this->getTimeSeries($viewable, $previousPeriod, $collection);
        $previousTotal = $previousTimeSeries->sum('total');
        
        // Calculate growth
        $growth = GrowthData::calculate($totalViews, $previousTotal);
        
        // Find peak and lowest points
        $peak = $timeSeries->sortByDesc('total')->first();
        $lowest = $timeSeries->sortBy('total')->first();
        
        // Calculate average
        $days = $period->getDays();
        $averageDaily = $days > 0 ? $totalViews / $days : 0;
        
        return new AnalyticsResult(
            period: $period->toData(),
            totalViews: $totalViews,
            uniqueViews: $uniqueViews,
            growth: $growth,
            timeSeries: $timeSeries,
            peak: $peak ?? $this->createEmptyPoint($period),
            lowest: $lowest ?? $this->createEmptyPoint($period),
            averageDaily: $averageDaily,
        );
    }
    
    /**
     * Get time series data for a period.
     * 
     * @return Collection<int, TimeSeriesPoint>
     */
    public function getTimeSeries(
        Model $viewable,
        Period $period,
        string $collection,
    ): Collection {
        $adapter = $this->calendarManager->adapter($period->getCalendar());
        $granularity = $period->getGranularity();
        
        // Get aggregated data
        $aggregates = ViewableAggregate::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->forGranularity($granularity)
            ->forCalendar($period->getCalendar())
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->orderBy('period_start')
            ->get()
            ->keyBy('period_key');
        
        // Generate all points in the period
        $points = collect();
        $current = $period->getStart()->copy();
        $previousTotal = 0;
        
        while ($current <= $period->getEnd()) {
            $key = $adapter->getPeriodKey($current, $granularity->value);
            $label = $adapter->getPeriodLabel($current, $granularity->value);
            
            $aggregate = $aggregates->get($key);
            $total = $aggregate?->total_views ?? 0;
            $unique = $aggregate?->unique_views ?? 0;
            
            // Add today's data from raw records if applicable
            if ($this->isToday($current) && config('viewable.analytics.include_today', true)) {
                $todayData = $this->getTodayData($viewable, $collection);
                $total += $todayData['total'];
                $unique += $todayData['unique'];
            }
            
            $growthPct = $previousTotal > 0 
                ? (($total - $previousTotal) / $previousTotal) * 100 
                : 0;
            
            $points->push(new TimeSeriesPoint(
                date: $current->copy(),
                label: $label,
                key: $key,
                total: $total,
                unique: $unique,
                growthPercentage: round($growthPct, 2),
            ));
            
            $previousTotal = $total;
            $current = $this->advanceDate($current, $granularity);
        }
        
        return $points;
    }
    
    /**
     * Get ranking of most viewed models.
     * 
     * @return Collection<int, array{model: Model, total_views: int, unique_views: int}>
     */
    public function getRanking(
        string $modelClass,
        Period $period,
        int $limit = 10,
        ?string $collection = null,
    ): Collection {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        
        $results = ViewableAggregate::query()
            ->select('viewable_id')
            ->selectRaw('SUM(total_views) as total_views')
            ->selectRaw('SUM(unique_views) as unique_views')
            ->where('viewable_type', (new $modelClass)->getMorphClass())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->forCalendar($period->getCalendar())
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->groupBy('viewable_id')
            ->orderByDesc('total_views')
            ->limit($limit)
            ->get();
        
        // Load the actual models
        $modelIds = $results->pluck('viewable_id');
        $models = $modelClass::whereIn('id', $modelIds)->get()->keyBy('id');
        
        return $results->map(function ($row) use ($models) {
            return [
                'model' => $models->get($row->viewable_id),
                'total_views' => (int) $row->total_views,
                'unique_views' => (int) $row->unique_views,
            ];
        })->filter(fn ($item) => $item['model'] !== null);
    }
    
    /**
     * Get trending models (fastest growing).
     */
    public function getTrending(
        string $modelClass,
        Period $period,
        int $limit = 10,
        int $minViews = 10,
        ?string $collection = null,
    ): Collection {
        $collection = $collection ?? config('viewable.collections.default', 'default');
        $previousPeriod = $period->previousPeriod();
        
        // Get current period views
        $currentViews = $this->getViewsByModel($modelClass, $period, $collection);
        
        // Get previous period views
        $previousViews = $this->getViewsByModel($modelClass, $previousPeriod, $collection);
        
        // Calculate growth and sort
        return $currentViews
            ->filter(fn ($views) => $views >= $minViews)
            ->map(function ($currentTotal, $modelId) use ($previousViews) {
                $previousTotal = $previousViews->get($modelId, 0);
                return [
                    'model_id' => $modelId,
                    'current' => $currentTotal,
                    'previous' => $previousTotal,
                    'growth' => GrowthData::calculate($currentTotal, $previousTotal),
                ];
            })
            ->sortByDesc(fn ($item) => $item['growth']->percentage)
            ->take($limit)
            ->map(function ($item) use ($modelClass) {
                $item['model'] = $modelClass::find($item['model_id']);
                return $item;
            })
            ->filter(fn ($item) => $item['model'] !== null);
    }
    
    // -------------------------------------------------------------------------
    // Protected methods
    // -------------------------------------------------------------------------
    
    protected function isToday($date): bool
    {
        return $date->toDateString() === $this->calendarManager->now()->toDateString();
    }
    
    protected function getTodayData(Model $viewable, string $collection): array
    {
        $today = $this->calendarManager->now()->startOfDay();
        
        $query = ViewableRecord::query()
            ->where('viewable_type', $viewable->getMorphClass())
            ->where('viewable_id', $viewable->getKey())
            ->where('collection', $collection)
            ->where('viewed_at', '>=', $today)
            ->forBranch();
        
        return [
            'total' => (clone $query)->count(),
            'unique' => (clone $query)->distinct('visitor_key')->count('visitor_key'),
        ];
    }
    
    protected function advanceDate($date, Granularity $granularity)
    {
        return match($granularity) {
            Granularity::Hourly => $date->addHour(),
            Granularity::Daily => $date->addDay(),
            Granularity::Weekly => $date->addWeek(),
            Granularity::Monthly => $date->addMonth(),
            Granularity::Yearly => $date->addYear(),
        };
    }
    
    protected function createEmptyPoint(Period $period): TimeSeriesPoint
    {
        return new TimeSeriesPoint(
            date: $period->getStart(),
            label: '',
            key: '',
            total: 0,
            unique: 0,
            growthPercentage: 0,
        );
    }
    
    protected function getViewsByModel(
        string $modelClass,
        Period $period,
        string $collection,
    ): Collection {
        return ViewableAggregate::query()
            ->select('viewable_id')
            ->selectRaw('SUM(total_views) as total_views')
            ->where('viewable_type', (new $modelClass)->getMorphClass())
            ->where('collection', $collection)
            ->forGranularity(Granularity::Daily)
            ->between($period->getStart(), $period->getEnd())
            ->forBranch()
            ->groupBy('viewable_id')
            ->pluck('total_views', 'viewable_id');
    }
}
```

---

## 11. Jobs

### src/Jobs/RecordViewJob.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use KarnoWeb\Viewable\DTOs\ViewData;
use KarnoWeb\Viewable\Services\ViewableService;

class RecordViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public ViewData $viewData,
    ) {
        $this->onConnection(config('viewable.performance.queue.connection', 'default'));
        $this->onQueue(config('viewable.performance.queue.queue', 'default'));
    }
    
    public function handle(ViewableService $service): void
    {
        $service->processView($this->viewData);
    }
}
```

### src/Jobs/CompressDailyViewsJob.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use KarnoWeb\Viewable\Branch\BranchManager;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Events\ViewsCompressed;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;

class CompressDailyViewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public ?string $date = null,
        public ?int $branchId = null,
    ) {}
    
    public function handle(CalendarManager $calendarManager, BranchManager $branchManager): void
    {
        // Determine the date to compress (default: yesterday)
        $targetDate = $this->date 
            ? $calendarManager->parse($this->date)
            : $calendarManager->now()->subDay();
        
        $startOfDay = $targetDate->copy()->startOfDay();
        $endOfDay = $targetDate->copy()->endOfDay();
        
        // Set branch context if provided
        if ($this->branchId !== null) {
            $branchManager->setBranchId($this->branchId);
        }
        
        $chunkSize = config('viewable.compression.chunk_size', 1000);
        $branchColumn = config('viewable.branch.column', 'branch_id');
        $branchEnabled = config('viewable.branch.enabled', false);
        
        // Get records table name
        $recordsTable = (new ViewableRecord)->getTable();
        
        // Build group by columns
        $groupByColumns = ['viewable_type', 'viewable_id', 'collection'];
        if ($branchEnabled) {
            $groupByColumns[] = $branchColumn;
        }
        
        // Query to get aggregated data
        $query = ViewableRecord::query()
            ->select($groupByColumns)
            ->selectRaw('COUNT(*) as total_views')
            ->selectRaw('COUNT(DISTINCT visitor_key) as unique_views')
            ->whereBetween('viewed_at', [$startOfDay, $endOfDay]);
        
        if ($branchEnabled && $this->branchId !== null) {
            $query->where($branchColumn, $this->branchId);
        }
        
        $query->groupBy($groupByColumns);
        
        // Process in chunks
        $processedCount = 0;
        
        $query->chunk($chunkSize, function ($records) use (
            $startOfDay,
            $endOfDay,
            $calendarManager,
            $branchEnabled,
            $branchColumn,
            &$processedCount,
        ) {
            foreach ($records as $record) {
                // Create aggregates for each calendar type
                foreach (CalendarType::cases() as $calendarType) {
                    $adapter = $calendarManager->adapter($calendarType);
                    $periodKey = $adapter->getPeriodKey($startOfDay, 'daily');
                    
                    $aggregateData = [
                        'viewable_type' => $record->viewable_type,
                        'viewable_id' => $record->viewable_id,
                        'collection' => $record->collection,
                        'calendar' => $calendarType->value,
                        'granularity' => Granularity::Daily->value,
                        'period_key' => $periodKey,
                        'period_start' => $startOfDay,
                        'period_end' => $endOfDay,
                    ];
                    
                    if ($branchEnabled) {
                        $aggregateData[$branchColumn] = $record->{$branchColumn};
                    }
                    
                    // Upsert the aggregate
                    ViewableAggregate::updateOrCreate(
                        collect($aggregateData)
                            ->except(['period_start', 'period_end'])
                            ->toArray(),
                        [
                            'total_views' => $record->total_views,
                            'unique_views' => $record->unique_views,
                            'period_start' => $startOfDay,
                            'period_end' => $endOfDay,
                        ]
                    );
                }
                
                $processedCount++;
            }
        });
        
        // Delete the raw records after successful compression
        $deleteQuery = ViewableRecord::query()
            ->whereBetween('viewed_at', [$startOfDay, $endOfDay]);
        
        if ($branchEnabled && $this->branchId !== null) {
            $deleteQuery->where($branchColumn, $this->branchId);
        }
        
        $deletedCount = $deleteQuery->delete();
        
        // Dispatch event
        event(new ViewsCompressed(
            date: $targetDate->toDateString(),
            recordsProcessed: $processedCount,
            recordsDeleted: $deletedCount,
            branchId: $this->branchId,
        ));
    }
}
```

---

## 12. Events

### src/Events/ViewRecorded.php

```php
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
```

### src/Events/ViewsCompressed.php

```php
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
```

---

## 13. Trait

### src/Traits/HasViews.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;
use KarnoWeb\Viewable\Enums\CalendarType;
use KarnoWeb\Viewable\Enums\Granularity;
use KarnoWeb\Viewable\Models\ViewableAggregate;
use KarnoWeb\Viewable\Models\ViewableRecord;
use KarnoWeb\Viewable\Services\AnalyticsService;
use KarnoWeb\Viewable\Services\ViewableService;

trait HasViews
{
    /**
     * Get all raw view records for this model.
     */
    public function viewRecords(): MorphMany
    {
        return $this->morphMany(ViewableRecord::class, 'viewable');
    }
    
    /**
     * Get all aggregated views for this model.
     */
    public function viewAggregates(): MorphMany
    {
        return $this->morphMany(ViewableAggregate::class, 'viewable');
    }
    
    /**
     * Record a view for this model.
     */
    public function recordView(?string $collection = null): bool
    {
        return app(ViewableService::class)->record($this, $collection);
    }
    
    /**
     * Get total views count.
     */
    public function viewsCount(?Period $period = null, ?string $collection = null): int
    {
        return app(ViewableService::class)->getViewsCount($this, $period, $collection);
    }
    
    /**
     * Get unique views count.
     */
    public function uniqueViewsCount(?Period $period = null, ?string $collection = null): int
    {
        return app(ViewableService::class)->getUniqueViewsCount($this, $period, $collection);
    }
    
    /**
     * Check if the current visitor has viewed this model.
     */
    public function hasBeenViewed(?string $collection = null): bool
    {
        return app(ViewableService::class)->hasViewed($this, $collection);
    }
    
    /**
     * Get analytics for this model.
     */
    public function analytics(
        ?Period $period = null,
        ?string $collection = null,
    ): AnalyticsResult {
        $period = $period ?? Period::lastDays(30);
        
        return app(AnalyticsService::class)->getAnalytics($this, $period, $collection);
    }
    
    /**
     * Scope to order by views count.
     */
    public function scopeMostViewed(Builder $query, ?Period $period = null): Builder
    {
        $period = $period ?? Period::thisMonth();
        $aggregatesTable = (new ViewableAggregate)->getTable();
        
        return $query
            ->leftJoin($aggregatesTable, function ($join) use ($period) {
                $join->on($this->getTable() . '.id', '=', $aggregatesTable . '.viewable_id')
                     ->where($aggregatesTable . '.viewable_type', '=', $this->getMorphClass())
                     ->where($aggregatesTable . '.granularity', '=', Granularity::Daily->value)
                     ->where($aggregatesTable . '.period_start', '>=', $period->getStart())
                     ->where($aggregatesTable . '.period_end', '<=', $period->getEnd());
            })
            ->select($this->getTable() . '.*')
            ->selectRaw("COALESCE(SUM({$aggregatesTable}.total_views), 0) as views_sum")
            ->groupBy($this->getTable() . '.id')
            ->orderByDesc('views_sum');
    }
    
    /**
     * Scope to get items with minimum views.
     */
    public function scopeWithMinViews(Builder $query, int $minViews, ?Period $period = null): Builder
    {
        return $this->scopeMostViewed($query, $period)
            ->having('views_sum', '>=', $minViews);
    }
}
```

---

## 14. Commands

### src/Commands/CompressViewsCommand.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Commands;

use Illuminate\Console\Command;
use KarnoWeb\Viewable\Jobs\CompressDailyViewsJob;

class CompressViewsCommand extends Command
{
    protected $signature = 'viewable:compress 
                            {--date= : The date to compress (default: yesterday)}
                            {--branch= : Specific branch ID to compress}
                            {--sync : Run synchronously instead of dispatching to queue}';
    
    protected $description = 'Compress raw view records into daily aggregates';
    
    public function handle(): int
    {
        $date = $this->option('date');
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $sync = $this->option('sync');
        
        $this->info("Compressing views for date: " . ($date ?? 'yesterday'));
        
        if ($branchId !== null) {
            $this->info("Branch ID: {$branchId}");
        }
        
        $job = new CompressDailyViewsJob($date, $branchId);
        
        if ($sync) {
            $job->handle(
                app(\KarnoWeb\Viewable\Calendar\CalendarManager::class),
                app(\KarnoWeb\Viewable\Branch\BranchManager::class)
            );
            $this->info('Compression completed synchronously.');
        } else {
            dispatch($job);
            $this->info('Compression job dispatched to queue.');
        }
        
        return self::SUCCESS;
    }
}
```

### src/Commands/PruneViewsCommand.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Commands;

use Illuminate\Console\Command;
use KarnoWeb\Viewable\Calendar\CalendarManager;
use KarnoWeb\Viewable\Models\ViewableRecord;

class PruneViewsCommand extends Command
{
    protected $signature = 'viewable:prune 
                            {--days= : Delete records older than X days}
                            {--dry-run : Show what would be deleted without actually deleting}';
    
    protected $description = 'Prune old raw view records';
    
    public function handle(CalendarManager $calendarManager): int
    {
        $days = (int) ($this->option('days') ?? config('viewable.compression.keep_raw_days', 1));
        $dryRun = $this->option('dry-run');
        
        $cutoff = $calendarManager->now()->subDays($days)->endOfDay();
        
        $query = ViewableRecord::query()->where('viewed_at', '<', $cutoff);
        $count = $query->count();
        
        $this->info("Found {$count} records older than {$days} days.");
        
        if ($dryRun) {
            $this->warn('Dry run - no records deleted.');
            return self::SUCCESS;
        }
        
        if ($count === 0) {
            $this->info('No records to prune.');
            return self::SUCCESS;
        }
        
        if ($this->confirm("Delete {$count} records?")) {
            $deleted = $query->delete();
            $this->info("Deleted {$deleted} records.");
        }
        
        return self::SUCCESS;
    }
}
```

---

## 15. Middleware

### src/Http/Middleware/RecordViewMiddleware.php

```php
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
```

---

## 16. Facade

### src/Facades/Viewable.php

```php
<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\DTOs\AnalyticsResult;

/**
 * @method static bool record(Model $viewable, ?string $collection = null)
 * @method static int getViewsCount(Model $viewable, ?Period $period = null, ?string $collection = null)
 * @method static int getUniqueViewsCount(Model $viewable, ?Period $period = null, ?string $collection = null)
 * @method static bool hasViewed(Model $viewable, ?string $collection = null)
 * @method static AnalyticsResult getAnalytics(Model $viewable, Period $period, ?string $collection = null)
 * @method static Collection getRanking(string $modelClass, Period $period, int $limit = 10, ?string $collection = null)
 * @method static Collection getTrending(string $modelClass, Period $period, int $limit = 10, int $minViews = 10, ?string $collection = null)
 * 
 * @see \KarnoWeb\Viewable\Services\ViewableService
 * @see \KarnoWeb\Viewable\Services\AnalyticsService
 */
class Viewable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'viewable';
    }
}
```

---

## 17. Service Provider

### src/ViewableServiceProvider.php

```php
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
```

---

## 📖 Usage Examples

```php
<?php

use App\Models\Post;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\Facades\Viewable;

// 1. Add trait to your model
class Post extends Model
{
    use \KarnoWeb\Viewable\Traits\HasViews;
}

// 2. Record a view
$post->recordView();
$post->recordView('api'); // with collection

// 3. Get view counts
$post->viewsCount();
$post->viewsCount(Period::thisMonth());
$post->viewsCount(Period::jalaliMonth(1402, 9));
$post->uniqueViewsCount();

// 4. Get analytics
$analytics = $post->analytics(Period::lastDays(30));
$analytics->totalViews;         // 1500
$analytics->uniqueViews;        // 800
$analytics->growth->percentage; // 25.5
$analytics->growth->trend;      // Trend::Up
$analytics->timeSeries;         // Collection of TimeSeriesPoint
$analytics->forChart();         // Ready for charts

// 5. Rankings
Viewable::getRanking(Post::class, Period::thisWeek(), limit: 10);
Viewable::getTrending(Post::class, Period::lastDays(7));

// 6. Jalali calendar
$analytics = $post->analytics(Period::jalaliThisMonth());
// Labels will be in Persian: "۱۵ آذر ۱۴۰۲"

// 7. Query scopes
Post::mostViewed(Period::thisMonth())->take(10)->get();
Post::withMinViews(100)->get();
```

---

ادامه بدم با تست‌ها و README؟