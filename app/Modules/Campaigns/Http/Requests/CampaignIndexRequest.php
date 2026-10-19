<?php

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignListQuery`.
 */
class CampaignIndexRequest extends FormRequest
{
    public const SORTABLE = ['updated_at', 'name', 'sent_at', 'scheduled_at', 'created_at'];

    public const TABS = ['all', 'draft', 'scheduled', 'running', 'sent', 'suspended', 'archived'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sorts = [...self::SORTABLE, ...array_map(fn (string $s): string => '-'.$s, self::SORTABLE)];

        return [
            'status_tab' => ['sometimes', 'nullable', Rule::in(self::TABS)],
            'status' => ['sometimes', 'nullable', 'string', 'max:20'],
            'folder_id' => ['sometimes', 'nullable', 'string'],
            'tag' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'nullable', Rule::in(['classic', 'ab_test'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
            'sort' => ['sometimes', 'nullable', Rule::in($sorts)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
