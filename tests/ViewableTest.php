<?php

declare(strict_types=1);

namespace KarnoWeb\Viewable\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use KarnoWeb\Viewable\Calendar\Period;
use KarnoWeb\Viewable\Models\ViewableRecord;
use KarnoWeb\Viewable\Traits\HasViews;
use Orchestra\Testbench\TestCase;

class ViewableTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return ['KarnoWeb\Viewable\ViewableServiceProvider'];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_can_record_view(): void
    {
        $post = Post::create(['title' => 'Test Post', 'content' => 'Test content']);

        $result = $post->recordView();

        $this->assertTrue($result);
        $this->assertDatabaseHas('vw_records', [
            'viewable_type' => Post::class,
            'viewable_id' => $post->id,
            'collection' => 'default',
        ]);
    }

    public function test_can_get_view_count(): void
    {
        $post = Post::create(['title' => 'Test Post', 'content' => 'Test content']);

        $post->recordView();
        $post->recordView();

        $this->assertEquals(2, $post->viewsCount());
    }

    public function test_can_get_unique_view_count(): void
    {
        $post = Post::create(['title' => 'Test Post', 'content' => 'Test content']);

        // Simulate same visitor
        $this->app['session']->put('viewable_visitor', 'test-visitor');

        $post->recordView();
        $post->recordView(); // Should be ignored due to cooldown

        $this->assertEquals(1, $post->uniqueViewsCount());
    }

    public function test_analytics_work(): void
    {
        $post = Post::create(['title' => 'Test Post', 'content' => 'Test content']);

        $post->recordView();

        $analytics = $post->analytics(Period::today());

        $this->assertInstanceOf(\KarnoWeb\Viewable\DTOs\AnalyticsResult::class, $analytics);
        $this->assertEquals(1, $analytics->totalViews);
    }
}

// Test Model
class Post extends \Illuminate\Database\Eloquent\Model
{
    use HasViews;

    protected $fillable = ['title', 'content'];

    public $timestamps = false;
}
