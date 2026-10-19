<?php

namespace App\Modules\Recipients\Segments\Http\Requests;

use App\Modules\Recipients\Models\Segment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.5.2 — `SegmentInput` (+ legacy `recipient_list_id`, §45.10).
 */
class StoreSegmentRequest extends FormRequest
{
    use ValidatesRuleTree;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Segment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'folder_id' => ['nullable', 'string', 'max:64'],
            'rules' => ['required', 'array'],
            'recipient_list_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
