<?php

namespace App\Modules\Recipients\Segments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.5.2 — `GET /segments/{id}/members` (`cursor`, `per_page`, `columns`).
 */
class SegmentMembersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'columns' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
