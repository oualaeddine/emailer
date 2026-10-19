<?php

namespace App\Modules\Recipients\Segments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.5.2 — partial `SegmentInput` (authorization in the controller,
 * once the segment is resolved).
 */
class UpdateSegmentRequest extends FormRequest
{
    use ValidatesRuleTree;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'folder_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'rules' => ['sometimes', 'required', 'array'],
            'recipient_list_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
