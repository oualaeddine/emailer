<?php

namespace App\Modules\DeliveryEngine\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/18-smtp-management.md §18.2 — password is write-only, never
 * redisplayed (docs/28-security.md §28.6) — the model already hides
 * `password_encrypted` from array/JSON output, so it is simply absent here.
 *
 * @mixin \App\Modules\DeliveryEngine\Models\SmtpAccount
 */
class SmtpAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'provider' => $this->provider,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'daily_quota' => $this->daily_quota,
            'hourly_quota' => $this->hourly_quota,
            'minute_quota' => $this->minute_quota,
            'max_concurrent_connections' => $this->max_concurrent_connections,
            'max_messages_per_connection' => $this->max_messages_per_connection,
            'priority' => $this->priority,
            'rotation_weight' => $this->rotation_weight,
            'warmup_enabled' => $this->warmup_enabled,
            'warmup_schedule_id' => $this->warmupSchedule?->uuid,
            'health_status' => $this->health_status,
            'bounce_rate' => $this->bounce_rate,
            'complaint_rate' => $this->complaint_rate,
            'reputation_score' => $this->reputation_score,
            'is_active' => $this->is_active,
            'last_tested_at' => $this->last_tested_at?->toIso8601String(),
        ];
    }
}
