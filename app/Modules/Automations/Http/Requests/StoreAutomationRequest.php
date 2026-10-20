<?php

namespace App\Modules\Automations\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Services\AutomationValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** docs/45 §45.7.5 — `AutomationInput` (POST; optional `steps` = AutomationStepInput[]). */
class StoreAutomationRequest extends FormRequest
{
    protected bool $partial = false;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission(PermissionName::AutomationsManage->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'min:1', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'trigger' => [$required, 'array'],
            'trigger.type' => ['required_with:trigger', 'string', Rule::enum(AutomationTriggerType::class)],
            'trigger.config' => ['sometimes', 'nullable', 'array'],
            'allow_reentry' => ['sometimes', 'boolean'],
            'reentry_after_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'steps' => $this->partial ? ['prohibited'] : ['sometimes', 'array'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $automationValidator = app(AutomationValidator::class);
            $errors = [];

            if (is_array($this->input('trigger'))) {
                $errors += $automationValidator->triggerErrors($this->input('trigger'), false);
            }

            if (! $this->partial && $this->has('steps')) {
                $errors += $automationValidator->stepsErrors($this->input('steps'), false);
            }

            foreach ($errors as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        }];
    }
}
