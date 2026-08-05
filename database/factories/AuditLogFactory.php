<?php

namespace Database\Factories;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * docs/27-audit-logs.md §27.3 — captured fields.
 *
 * `AuditLog` does not use the `HasFactory` trait (it is a read-only
 * reference model for this work package, docs/42-parallel-execution-plan.md
 * §42.8 — `app/Modules/Audit/Models/AuditLog.php` may not be modified), so
 * tests instantiate this factory directly via `AuditLogFactory::new()`
 * rather than the usual `AuditLog::factory()` static proxy (which requires
 * the trait). `Factory::new()`/`create()` work regardless, since they only
 * depend on `$model` below, not on the model class itself.
 *
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                'campaign.created',
                'campaign.sent',
                'smtp_account.updated',
                'user.role_changed',
                'suppression.added_manual',
            ]),
            'auditable_type' => 'App\\Modules\\Campaigns\\Models\\Campaign',
            'auditable_id' => fake()->numberBetween(1, 1000),
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'sent'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function system(): static
    {
        return $this->state(['user_id' => null]);
    }

    public function forAction(string $action): static
    {
        return $this->state(['action' => $action]);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function withValues(?array $old, ?array $new): static
    {
        return $this->state(['old_values' => $old, 'new_values' => $new]);
    }
}
