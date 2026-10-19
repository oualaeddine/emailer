<?php

namespace App\Modules\Suppression\Http\Requests;

use App\Domain\Enums\SuppressionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/** GET /api/v1/suppression-entries — `reason` may be a scalar or an array. */
class IndexSuppressionEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => [$this->input('reason')]]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'array'],
            'reason.*' => [new Enum(SuppressionReason::class)],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:created_at,-created_at,email,-email,reason,-reason'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
