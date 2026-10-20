<?php

namespace App\Modules\Templates\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/45 §45.4.4 — POST /templates/import-from-url.
 */
class ImportTemplateFromUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::TemplatesManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['url' => ['required', 'string', 'url:http,https', 'max:2048']];
    }
}
