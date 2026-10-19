<?php

namespace App\Modules\Senders\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class StoreSendingDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SendersManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge(['domain' => strtolower(trim($this->input('domain')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'domain' => [
                'required', 'string', 'max:253',
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                'unique:sending_domains,domain',
            ],
        ];
    }
}
