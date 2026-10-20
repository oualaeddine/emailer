<?php

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public const TABS = ['overview', 'deliverability', 'opens', 'clicks', 'unsubscriptions'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['tab' => ['required', Rule::in(self::TABS)]];
    }
}
