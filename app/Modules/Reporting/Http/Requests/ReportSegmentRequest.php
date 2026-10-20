<?php

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `ReportSegmentInput` (docs/45 §45.6.5).
 */
class ReportSegmentRequest extends FormRequest
{
    public const METRICS = ['opened', 'not_opened', 'clicked', 'not_clicked', 'unsubscribed', 'hard_bounced', 'soft_bounced'];

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
            'metric' => ['required', Rule::in(self::METRICS)],
            'link_id' => ['nullable', 'string', 'max:16'],
            'name' => ['nullable', 'string', 'max:150'],
            'folder_id' => ['nullable', 'uuid'],
        ];
    }
}
