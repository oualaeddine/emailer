<?php

namespace App\Modules\Recipients\Segments\Http\Requests;

use App\Modules\Recipients\Models\Segment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.5.2 — `POST /segments/count` `{ "rules": RuleTree }`.
 */
class CountSegmentRequest extends FormRequest
{
    use ValidatesRuleTree;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Segment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['rules' => ['required', 'array']];
    }
}
