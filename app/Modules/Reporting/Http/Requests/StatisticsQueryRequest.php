<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Services\StatisticsService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * docs/45 §45.6.5 `StatisticsQuery` (+ `granularity`, `sort`, `tag`, `table`, `period`) shared by every
 * `/statistics/*` endpoint. Authorization is done in the controllers.
 */
class StatisticsQueryRequest extends FormRequest
{
    public const MAX_DAYS = 400;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['nullable', Rule::in(array_keys(StatisticsService::PERIODS))],
            'scope' => ['nullable', Rule::in(['marketing', 'transactional', 'all'])],
            'compare' => ['nullable', Rule::in(['previous_period', 'none'])],
            'granularity' => ['nullable', Rule::in(['hour', 'day', 'week', 'month'])],
            'sort' => ['nullable', Rule::in(StatisticsService::SORTS)],
            'tag' => ['nullable', 'string', 'max:100'],
            'table' => ['nullable', Rule::in(['campaigns', 'lists'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $from = $this->input('date_from') ?? $this->input('from');
            $to = $this->input('date_to') ?? $this->input('to');
            if ($from === null || $to === null) {
                return;
            }

            $start = Carbon::parse($from, 'UTC')->startOfDay();
            $end = Carbon::parse($to, 'UTC')->startOfDay();
            if ($end->lt($start)) {
                $v->errors()->add('date_to', 'La date de fin doit être postérieure ou égale à la date de début.');
            } elseif ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
                $v->errors()->add('date_to', 'La période ne peut pas dépasser '.self::MAX_DAYS.' jours.');
            } elseif (($this->input('granularity') === 'hour') && $start->diffInDays($end) + 1 > 31) {
                $v->errors()->add('granularity', 'La granularité horaire est limitée à 31 jours.');
            }
        });
    }
}
