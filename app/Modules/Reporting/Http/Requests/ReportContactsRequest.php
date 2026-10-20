<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Services\ReportQueryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportContactsRequest extends FormRequest
{
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
            'metric' => ['required', Rule::in(ReportQueryService::METRICS)],
            'link_id' => ['nullable', 'string', 'max:16'],
            'list_id' => ['nullable', 'uuid'],
            'cursor' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
