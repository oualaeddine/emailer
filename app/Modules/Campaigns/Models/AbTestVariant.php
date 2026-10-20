<?php

namespace App\Modules\Campaigns\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\AbTestVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45-brevo-api-contracts.md §45.7.2 / §45.8 Slot O — one version (A or B)
 * of an A/B test campaign (docs/43 §43.6.2). Null `subject` / `html_body`
 * fall back to the campaign's own subject / design (subject tests keep the
 * campaign design, content tests may keep the campaign subject).
 *
 * @property int $id
 * @property string $uuid
 * @property int $campaign_id
 * @property string $label
 * @property string|null $subject
 * @property string|null $preview_text
 * @property string|null $html_body
 * @property array<string, mixed>|null $design_json
 * @property bool $is_winner
 * @property int $sample_size
 */
class AbTestVariant extends Model
{
    /** @use HasFactory<AbTestVariantFactory> */
    use HasFactory, HasUuid;

    public const LABEL_A = 'A';

    public const LABEL_B = 'B';

    public const LABELS = [self::LABEL_A, self::LABEL_B];

    protected $fillable = [
        'campaign_id', 'label', 'subject', 'preview_text', 'html_body', 'design_json', 'is_winner', 'sample_size',
    ];

    protected function casts(): array
    {
        return [
            'design_json' => 'array',
            'is_winner' => 'boolean',
            'sample_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    protected static function newFactory(): AbTestVariantFactory
    {
        return AbTestVariantFactory::new();
    }
}
