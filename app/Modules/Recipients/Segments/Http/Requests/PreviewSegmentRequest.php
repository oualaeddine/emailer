<?php

namespace App\Modules\Recipients\Segments\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Legacy `POST /segments/preview` and `POST /segments/{id}/preview`
 * (doc 29 §29.4, kept by docs/45 §45.10) — permission `segments.manage`.
 * `rules` is required without a segment, optional with one.
 */
class PreviewSegmentRequest extends FormRequest
{
    use ValidatesRuleTree;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SegmentsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['rules' => [$this->route('id') === null ? 'required' : 'sometimes', 'array']];
    }
}
