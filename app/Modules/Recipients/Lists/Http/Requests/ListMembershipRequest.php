<?php

namespace App\Modules\Recipients\Lists\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** docs/45 §45.5.6 `ListMembershipInput`. */
class ListMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::RecipientsManage->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:add,remove'],
            'recipient_ids' => ['sometimes', 'nullable', 'array', 'max:1000'],
            'recipient_ids.*' => ['uuid'],
            'emails' => ['sometimes', 'nullable', 'array', 'max:1000'],
            'emails.*' => ['string', 'max:255'],
            'selection' => ['sometimes', 'nullable', 'array'],
            'selection.ids' => ['sometimes', 'array', 'min:1', 'max:1000'],
            'selection.ids.*' => ['uuid'],
            'selection.all_matching' => ['sometimes', 'array'],
            'selection.exclude_ids' => ['sometimes', 'array'],
            'selection.exclude_ids.*' => ['uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $given = array_filter([
                ! empty($this->input('recipient_ids')),
                ! empty($this->input('emails')),
                ! empty($this->input('selection')),
            ]);
            if (count($given) === 0) {
                $v->errors()->add('recipient_ids', 'Indiquez des contacts, des adresses e-mail ou une sélection.');
            }
            $selection = $this->input('selection');
            if (is_array($selection) && isset($selection['ids']) === isset($selection['all_matching'])) {
                $v->errors()->add('selection', 'Exactement une des clés « ids » ou « all_matching » est requise.');
            }
        });
    }
}
