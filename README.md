# Laravel Viewable Package

A powerful view tracking and analytics package for Laravel with Jalali/Gregorian calendar support, multi-tenancy, and comprehensive analytics.

## Features

- 🚀 **High Performance**: Asynchronous view recording with queue support
- 📊 **Advanced Analytics**: Time series, growth tracking, rankings, and trending
- 🌍 **Multi-Calendar**: Support for both Gregorian and Jalali (Persian) calendars
- 🏢 **Multi-Tenant**: Branch-based multi-tenancy support
- 🤖 **Bot Detection**: Automatic bot filtering
- ⏰ **Cooldown System**: Prevent spam views from same visitor
- 📈 **Compression**: Automatic data compression for performance
- 🎯 **Collections**: Categorize views (web, api, admin, etc.)
- 🔄 **Middleware**: Automatic view recording via middleware

## Installation

```bash
composer require karnoweb/laravel-viewable
```

### Publish Configuration and Migrations

```bash
php artisan vendor:publish --provider="KarnoWeb\Viewable\ViewableServiceProvider" --tag=viewable-config
php artisan vendor:publish --provider="KarnoWeb\Viewable\ViewableServiceProvider" --tag=viewable-migrations
php artisan migrate
```

## Quick Start

### 1. Add Trait to Your Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use KarnoWeb\Viewable\Traits\HasViews;

class Post extends Model
{
    use HasViews;

    // Your model code...
}
```

### 2. Record Views

```php
// Manual recording
$post->recordView();
$post->recordView('api'); // with collection

// Using middleware (automatic)
Route::get('/posts/{post}', [PostController::class, 'show'])
    ->middleware('viewable:post');
```

### 3. Get View Counts

```php
// Total views
$total = $post->viewsCount();

// Unique views
$unique = $post->uniqueViewsCount();

// Views for specific period
use KarnoWeb\Viewable\Calendar\Period;

$monthlyViews = $post->viewsCount(Period::thisMonth());
$weeklyViews = $post->viewsCount(Period::thisWeek());
```

### 4. Analytics

```php
$analytics = $post->analytics(Period::lastDays(30));

echo "Total Views: {$analytics->totalViews}\n";
echo "Unique Views: {$analytics->uniqueViews}\n";
echo "Growth: {$analytics->growth->percentage}%\n";
echo "Trend: {$analytics->growth->trend->label()}\n";

// Chart data
$chartData = $analytics->forChart();
```

### 5. Rankings and Trending

```php
use KarnoWeb\Viewable\Facades\Viewable;

// Top viewed posts this month
$topPosts = Viewable::getRanking(Post::class, Period::thisMonth(), limit: 10);

// Trending posts (fastest growing)
$trending = Viewable::getTrending(Post::class, Period::lastDays(7));
```

## Configuration

### Calendar Settings

```php
// config/viewable.php
'calendar' => [
    'default' => 'gregorian', // or 'jalali'
    'timezone' => 'Asia/Tehran',
    'week_starts_on' => 6, // Saturday
    'jalali' => [
        'locale' => 'fa',
        'numbers' => 'latin', // or 'persian'
    ],
],
```

### Jalali Calendar Usage

```php
// Jalali periods
$jalaliMonth = Period::jalaliMonth(1402, 9); // Mehr 1402
$jalaliYear = Period::jalaliYear(1402);

// Analytics with Jalali calendar
$analytics = $post->analytics(Period::jalaliThisMonth());
```

### Multi-Tenant Support

```php
// Enable branch support
'branch' => [
    'enabled' => true,
    'column' => 'branch_id',
    'resolver' => \App\Resolvers\CustomBranchResolver::class,
],
```

### Queue and Performance

```php
'performance' => [
    'queue' => [
        'enabled' => true,
        'connection' => 'default',
        'queue' => 'default',
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
],
```

## Commands

### Compress Views

Compress raw records into daily aggregates:

```bash
# Compress yesterday's views
php artisan viewable:compress

# Compress specific date
php artisan viewable:compress --date=2024-01-15

# Run synchronously
php artisan viewable:compress --sync
```

### Prune Old Records

Remove old raw view records:

```bash
# Prune records older than 7 days
php artisan viewable:prune --days=7

# Dry run
php artisan viewable:prune --dry-run
```

## Advanced Usage

### Custom Branch Resolver

```php
<?php

namespace App\Resolvers;

use KarnoWeb\Viewable\Contracts\BranchResolverContract;

class CustomBranchResolver implements BranchResolverContract
{
    public function resolve(): ?int
    {
        // Your custom logic
        return auth()->user()->current_branch_id ?? null;
    }
}
```

### Query Scopes

```php
// Get most viewed posts
$popularPosts = Post::mostViewed(Period::thisMonth())->take(10)->get();

// Posts with minimum views
$featuredPosts = Post::withMinViews(100)->get();
```

### Events

Listen to view events:

```php
// ViewRecorded event
Event::listen(\KarnoWeb\Viewable\Events\ViewRecorded::class, function ($event) {
    // $event->viewData contains view information
});

// ViewsCompressed event
Event::listen(\KarnoWeb\Viewable\Events\ViewsCompressed::class, function ($event) {
    // $event->recordsProcessed, $event->recordsDeleted
});
```

## Requirements

- PHP 8.1+
- Laravel 10.0+ or 11.0+
- MySQL 5.7+ / PostgreSQL 9.5+ / SQLite 3.8.8+

## License

MIT License

## Support

For issues and questions, please create an issue on GitHub.
