<?php

namespace App\Modules\Recipients\Views\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * docs/45 §45.3 — ContactSelection validation shared by bulk and export.
 */
final class ContactSelectionRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'selection' => ['required', 'array'],
            'selection.ids' => ['nullable', 'array', 'min:1', 'max:1000'],
            'selection.ids.*' => ['string', 'max:64'],
            'selection.all_matching' => ['nullable', 'array'],
            'selection.all_matching.q' => ['nullable', 'string', 'max:255'],
            'selection.all_matching.list_id' => ['nullable', 'string', 'max:64'],
            'selection.all_matching.segment_id' => ['nullable', 'string', 'max:64'],
            'selection.all_matching.view_id' => ['nullable', 'string', 'max:64'],
            'selection.all_matching.filters' => ['nullable', 'array'],
            'selection.all_matching.scope' => ['nullable', 'in:all,mine'],
            'selection.exclude_ids' => ['nullable', 'array', 'max:100000'],
            'selection.exclude_ids.*' => ['string', 'max:64'],
        ];
    }

    /**
     * @return list<callable>
     */
    public static function after(FormRequest $request): array
    {
        return [function (Validator $validator) use ($request): void {
            $selection = (array) $request->input('selection', []);
            $hasIds = ! empty($selection['ids']);
            $hasAll = isset($selection['all_matching']) && is_array($selection['all_matching']);

            if ($hasIds === $hasAll) {
                $validator->errors()->add('selection', 'Fournissez soit « ids », soit « all_matching ».');
            }
        }];
    }
}
