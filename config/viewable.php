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
