<?php

namespace App\Modules\Forms\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45 §45.8 Slot Q — `form_submissions`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property int|null $recipient_id
 * @property string $email
 * @property string $status pending_confirmation|confirmed|subscribed|rejected|spam
 * @property array<string, mixed> $data
 * @property string|null $ip_hash
 * @property string|null $ip_country
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 */
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory, HasUuid;

    public const STATUSES = ['pending_confirmation', 'confirmed', 'subscribed', 'rejected', 'spam'];

    protected $fillable = [
        'form_id', 'recipient_id', 'email', 'status', 'data', 'confirmation_token_hash',
        'ip_hash', 'ip_country', 'user_agent', 'submitted_at', 'confirmed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['data' => 'array', 'submitted_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    protected static function newFactory(): FormSubmissionFactory
    {
        return FormSubmissionFactory::new();
    }
}
