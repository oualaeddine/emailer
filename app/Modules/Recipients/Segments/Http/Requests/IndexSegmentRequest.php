<?php

namespace App\Modules\Recipients\Segments\Http\Requests;

use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Segments\Services\SegmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/45 §45.5.2 — `GET /segments` (`q`, `folder_id` | `none`, `sort`, `page`).
 */
class IndexSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Segment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'folder_id' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', Rule::in(SegmentService::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
