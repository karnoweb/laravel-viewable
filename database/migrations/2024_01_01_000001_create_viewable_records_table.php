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
