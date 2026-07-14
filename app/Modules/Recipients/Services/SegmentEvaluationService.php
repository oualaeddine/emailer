<?php

namespace App\Modules\Recipients\Services;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\Segment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * docs/13-recipient-management.md §13.7 — Lists vs. Segments, rule tree
 * structure and evaluation. Compiles `segments.rules` (a rule tree, see
 * §13.7 example) into a query against `recipients`, always excluding
 * suppressed recipients regardless of the rule tree — a non-overridable
 * safety rule (§13.7 and §23.6), not a configurable option.
 */
class SegmentEvaluationService
{
    private const CACHE_TTL_MINUTES = 15;

    /** @var list<string> */
    private const ALLOWED_RECIPIENT_FIELDS = [
        'status', 'source', 'email', 'first_name', 'last_name', 'company_name', 'last_contacted_at',
    ];

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

        $query = $this->compile($segment->rules);
        $results = $query->get();

        Cache::put($cacheKey, $results, now()->addMinutes(self::CACHE_TTL_MINUTES));

        $segment->update(['last_evaluated_at' => now(), 'cached_count' => $results->count()]);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return Builder<Recipient>
     */
    public function compile(array $rules): Builder
    {
        $query = Recipient::query()->where('status', '!=', RecipientStatus::Suppressed->value);

        $this->applyGroup($query, $rules);

        return $query;
    }

    /**
     * @param  Builder<Recipient>  $query
     * @param  array<string, mixed>  $group
     */
    private function applyGroup(Builder $query, array $group): void
    {
        $operator = strtoupper($group['operator'] ?? 'AND');
        $conditions = $group['conditions'] ?? [];
        $boolean = $operator === 'OR' ? 'or' : 'and';

        $query->where(function (Builder $inner) use ($conditions, $boolean): void {
            foreach ($conditions as $condition) {
                $method = isset($condition['conditions']) ? 'applyNestedGroup' : 'applyCondition';
                $this->{$method}($inner, $condition, $boolean);
            }
        });
    }

    /**
     * @param  Builder<Recipient>  $query
     * @param  array<string, mixed>  $group
     */
    private function applyNestedGroup(Builder $query, array $group, string $boolean): void
    {
        $query->where(function (Builder $inner) use ($group): void {
            $this->applyGroup($inner, $group);
        }, boolean: $boolean);
    }

    /**
     * @param  Builder<Recipient>  $query
     * @param  array<string, mixed>  $condition
     */
    private function applyCondition(Builder $query, array $condition, string $boolean): void
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        if ($field === 'tags') {
            $this->applyTagsCondition($query, $operator, $value, $boolean);

            return;
        }

        if (str_starts_with($field, 'custom_fields.')) {
            $this->applyCustomFieldCondition($query, substr($field, strlen('custom_fields.')), $operator, $value, $boolean);

            return;
        }

        if (str_starts_with($field, 'pagejaunes_company_cache.')) {
            $this->applyPageJaunesCondition($query, substr($field, strlen('pagejaunes_company_cache.')), $operator, $value, $boolean);

            return;
        }

        if (! in_array($field, self::ALLOWED_RECIPIENT_FIELDS, true)) {
            throw new InvalidArgumentException("Champ de segment non autorisé : {$field}");
        }

        $this->applyScalarCondition($query, $field, $operator, $value, $boolean);
    }

    /**
     * @param  Builder<Recipient>  $query
     */
    private function applyScalarCondition(Builder $query, string $column, string $operator, mixed $value, string $boolean): void
    {
        match ($operator) {
            'equals' => $query->where($column, '=', $value, $boolean),
            'not_equals' => $query->where($column, '!=', $value, $boolean),
            'contains' => $query->where($column, 'like', "%{$value}%", $boolean),
            'before' => $query->where($column, '<', $this->resolveDate($value), $boolean),
            'after' => $query->where($column, '>', $this->resolveDate($value), $boolean),
            'is_empty' => $query->whereNull($column, $boolean),
            'is_not_empty' => $query->whereNotNull($column, $boolean),
            default => throw new InvalidArgumentException("Opérateur de segment non pris en charge : {$operator}"),
        };
    }

    /**
     * @param  Builder<Recipient>  $query
     */
    private function applyTagsCondition(Builder $query, string $operator, mixed $value, string $boolean): void
    {
        if ($operator !== 'includes_any') {
            throw new InvalidArgumentException("Opérateur non pris en charge pour 'tags' : {$operator}");
        }

        $tagNames = (array) $value;
        $callback = fn (Builder $t) => $t->whereIn('name', $tagNames);

        if ($boolean === 'or') {
            $query->orWhereHas('tags', $callback);
        } else {
            $query->whereHas('tags', $callback);
        }
    }

    /**
     * @param  Builder<Recipient>  $query
     */
    private function applyCustomFieldCondition(Builder $query, string $key, string $operator, mixed $value, string $boolean): void
    {
        $path = "custom_fields->{$key}";

        match ($operator) {
            'equals' => $query->where($path, '=', $value, $boolean),
            'not_equals' => $query->where($path, '!=', $value, $boolean),
            'is_empty' => $query->whereNull($path, $boolean),
            'is_not_empty' => $query->whereNotNull($path, $boolean),
            default => throw new InvalidArgumentException("Opérateur non pris en charge pour un champ personnalisé : {$operator}"),
        };
    }

    /**
     * @param  Builder<Recipient>  $query
     */
    private function applyPageJaunesCondition(Builder $query, string $column, string $operator, mixed $value, string $boolean): void
    {
        $callback = function (Builder $company) use ($column, $operator, $value): void {
            match ($operator) {
                'equals' => $company->where($column, '=', $value),
                'not_equals' => $company->where($column, '!=', $value),
                'contains' => $company->where($column, 'like', "%{$value}%"),
                default => throw new InvalidArgumentException("Opérateur non pris en charge pour un champ PageJaunes : {$operator}"),
            };
        };

        if ($boolean === 'or') {
            $query->orWhereHas('pageJaunesCompany', $callback);
        } else {
            $query->whereHas('pageJaunesCompany', $callback);
        }
    }

    private function resolveDate(string $value): Carbon
    {
        if (preg_match('/^([+-]\d+)d$/', $value, $matches)) {
            return now()->addDays((int) $matches[1]);
        }

        return Carbon::parse($value);
    }
}
