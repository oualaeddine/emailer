<?php

namespace App\Modules\Automations\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Automations\Services\AutomationValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** docs/45 §45.7.5 — PUT /automations/{id}/steps: `{ steps: AutomationStepInput[] }` (whole tree). */
class ReplaceAutomationStepsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission(PermissionName::AutomationsManage->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['steps' => ['present', 'array']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (app(AutomationValidator::class)->stepsErrors($this->input('steps'), false) as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        }];
    }
}
