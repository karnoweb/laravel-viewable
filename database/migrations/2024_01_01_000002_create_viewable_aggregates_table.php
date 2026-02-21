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
            $table->timestamp('period_start')->useCurrent();
            $table->timestamp('period_end')->useCurrent();

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
