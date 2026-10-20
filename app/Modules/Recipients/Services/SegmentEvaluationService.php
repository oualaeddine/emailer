<?php

namespace App\Modules\Recipients\Services;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * docs/13-recipient-management.md §13.7 — segment *audience* evaluation,
 * extended by WP-54 (docs/45 §45.5.2): rule trees (v2, or legacy v1 which is
 * upgraded on the fly) are compiled by the §45.9 {@see RuleTreeCompiler}
 * (tags, typed custom attributes, list membership, engagement sub-queries on
 * `messages`, dates, PagesJaunes).
 *
 * Unlike the raw compiler (which keeps blocklisted contacts, §45.5.2), this
 * audience-facing service always excludes `status = suppressed` recipients —
 * the non-overridable safety rule of §13.7 / §23.6 used by campaign
 * audiences (WP-42 AudienceResolver).
 */
class SegmentEvaluationService
{
    private const CACHE_TTL_MINUTES = 15;

    public function __construct(private readonly RuleTreeCompiler $compiler) {}

    /**
     * @return Collection<int, Recipient>
     */
    public function evaluate(Segment $segment, bool $useCache = true): Collection
    {
        $cacheKey = "segments.{$segment->uuid}.members";

        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $results = $this->compile($segment->rules ?? [])->get();

        Cache::put($cacheKey, $results, now()->addMinutes(self::CACHE_TTL_MINUTES));

        $segment->update(['last_evaluated_at' => now(), 'cached_count' => $results->count()]);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $rules  v2 RuleTree or legacy v1 tree
     * @return Builder<Recipient>
     */
    public function compile(array $rules): Builder
    {
        return $this->compiler
            ->apply(Recipient::query(), $rules)
            ->where('recipients.status', '!=', RecipientStatus::Suppressed->value);
    }

    public function forget(Segment $segment): void
    {
        Cache::forget("segments.{$segment->uuid}.members");
    }
}
