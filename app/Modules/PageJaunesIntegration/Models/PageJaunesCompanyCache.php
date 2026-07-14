<?php

namespace App\Modules\PageJaunesIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/04-database-design.md §4.7 — `pagejaunes_company_cache`.
 * Lives on the app's own default connection (this is OUR cache, not a
 * model against the external `pagejaunes` connection).
 *
 * @property int $id
 * @property int $pagejaunes_company_id
 * @property string $pagejaunes_code
 * @property string|null $company_name
 * @property string|null $wilaya_label
 */
class PageJaunesCompanyCache extends Model
{
    protected $table = 'pagejaunes_company_cache';

    protected $fillable = [
        'pagejaunes_company_id',
        'pagejaunes_code',
        'company_name',
        'abv_company_name',
        'slug',
        'legal_form_label',
        'country_label',
        'wilaya_label',
        'locality_label',
        'address',
        'about',
        'is_distributor',
        'is_exporter',
        'is_importer',
        'is_producer',
        'is_customer',
        'nb_employee',
        'website',
        'main_picture_url',
        'source_created_at',
        'source_deleted_at',
        'raw_source_data',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_distributor' => 'boolean',
            'is_exporter' => 'boolean',
            'is_importer' => 'boolean',
            'is_producer' => 'boolean',
            'is_customer' => 'boolean',
            'raw_source_data' => 'array',
            'source_created_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Explicit FK: Laravel's relation-name guess from this model's class
     * name (`PageJaunesCompanyCache`) would produce `page_jaunes_company_cache_id`
     * (splitting "PageJaunes" on its capital J), which does not match our
     * actual column `pagejaunes_company_cache_id`.
     *
     * @return HasMany<PageJaunesCompanyEmailCache, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(PageJaunesCompanyEmailCache::class, 'pagejaunes_company_cache_id');
    }
}
