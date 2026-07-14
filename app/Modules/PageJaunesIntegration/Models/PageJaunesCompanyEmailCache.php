<?php

namespace App\Modules\PageJaunesIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.7 — `pagejaunes_company_emails_cache`.
 *
 * @property int $id
 * @property int $pagejaunes_company_cache_id
 * @property int $pagejaunes_email_id
 * @property string $email
 */
class PageJaunesCompanyEmailCache extends Model
{
    public $timestamps = false;

    protected $table = 'pagejaunes_company_emails_cache';

    protected $fillable = [
        'pagejaunes_company_cache_id',
        'pagejaunes_email_id',
        'email',
        'source_deleted_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'source_deleted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PageJaunesCompanyCache, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(PageJaunesCompanyCache::class, 'pagejaunes_company_cache_id');
    }
}
