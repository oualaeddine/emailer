<?php

namespace App\Modules\Webhooks\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Webhooks\Models\ApiWebhook;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** docs/45 §45.7.7 — `POST /webhooks/{id}/test` body. */
class TestWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::WebhooksManage->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['event' => ['required', 'string']];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $webhook = ApiWebhook::query()->where('uuid', $this->route('id'))->first();
            $event = $this->input('event') === 'sent' ? 'request' : $this->input('event');
            if ($webhook !== null && $validator->errors()->isEmpty() && ! in_array($event, ApiWebhook::eventsFor($webhook->type), true)) {
                $validator->errors()->add('event', 'Cet événement n’est pas disponible pour ce type de webhook.');
            }
        }];
    }
}
