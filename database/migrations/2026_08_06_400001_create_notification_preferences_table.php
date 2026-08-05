<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/41-notification-center.md §41.4 — `notification_preferences`
 * (schema addendum). Per-(user, category, channel) override rows layered
 * on top of the framework-standard `notifications` table (already created
 * by `2026_07_13_181721_create_notifications_table.php`) — a missing row
 * means "default: enabled", so no seeding is required on user creation;
 * see `App\Modules\Notifications\Services\NotificationPreferenceService`.
 * docs/42-parallel-execution-plan.md §42.10 slot D.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
