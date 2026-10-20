<?php

namespace App\Modules\Webhooks\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Support\Network\PublicUrlGuard;
use App\Support\Network\UnsafeUrlException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** docs/45 §45.7.7 — `WebhookInput` (POST full, PATCH partial). */
class WebhookRequest extends FormRequest
{
    private const RESERVED_HEADERS = ['host', 'content-type', 'content-length', 'user-agent', 'authorization-bypass'];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::WebhooksManage->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'url' => [$required, 'string', 'max:2048', 'url:http,https'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => [$required, Rule::in([ApiWebhook::TYPE_TRANSACTIONAL, ApiWebhook::TYPE_MARKETING])],
            'events' => [$required, 'array', 'min:1'],
            'events.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
            'headers' => ['nullable', 'array', 'max:20'],
            'headers.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $current = $this->route('id') ? ApiWebhook::query()->where('uuid', $this->route('id'))->first() : null;

            if ($this->has('url')) {
                $this->validateUrl($validator, (string) $this->input('url'));
            }

            if ($this->has('events') || $this->has('type')) {
                $type = $this->input('type', $current?->type ?? ApiWebhook::TYPE_TRANSACTIONAL);
                $events = $this->input('events', $current?->events ?? []);
                $allowed = ApiWebhook::eventsFor($type);
                foreach ($events as $i => $event) {
                    $normalized = $event === 'sent' ? 'request' : $event;
                    if (! in_array($normalized, $allowed, true)) {
                        $validator->errors()->add("events.$i", 'Cet événement n’est pas disponible pour ce type de webhook.');
                    }
                }
            }

            foreach (array_keys((array) $this->input('headers', [])) as $name) {
                $lower = strtolower((string) $name);
                if (preg_match('/^[A-Za-z0-9-]{1,64}$/', (string) $name) !== 1 || str_starts_with($lower, 'x-mailer-') || in_array($lower, self::RESERVED_HEADERS, true)) {
                    $validator->errors()->add("headers.$name", 'Nom d’en-tête invalide ou réservé.');
                }
            }
        }];
    }

    private function validateUrl(Validator $validator, string $url): void
    {
        if (! str_starts_with(strtolower($url), 'https://') && app()->isProduction()) {
            $validator->errors()->add('url', 'L’URL doit utiliser HTTPS.');

            return;
        }
        try {
            app(PublicUrlGuard::class)->resolve($url);
        } catch (UnsafeUrlException $e) {
            $validator->errors()->add('url', match ($e->reason) {
                UnsafeUrlException::UNRESOLVED => 'Cet hôte est introuvable.',
                UnsafeUrlException::PRIVATE => 'L’URL doit pointer vers un hôte public (adresses privées interdites).',
                default => 'URL invalide.',
            });
        }
    }
}
