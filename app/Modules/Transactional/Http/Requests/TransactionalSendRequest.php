<?php

namespace App\Modules\Transactional\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** docs/45 §45.7.6 — TransactionalSendInput. Permission/scope is checked by RequireApiScope. */
class TransactionalSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1', 'max:50'],
            'to.*.email' => ['required', 'email:rfc', 'max:255'],
            'to.*.name' => ['nullable', 'string', 'max:150'],
            'cc' => ['nullable', 'array', 'max:50'],
            'cc.*.email' => ['required', 'email:rfc'],
            'cc.*.name' => ['nullable', 'string', 'max:150'],
            'bcc' => ['nullable', 'array', 'max:50'],
            'bcc.*.email' => ['required', 'email:rfc'],
            'bcc.*.name' => ['nullable', 'string', 'max:150'],
            'reply_to' => ['nullable', 'array'],
            'reply_to.email' => ['required_with:reply_to', 'email:rfc'],
            'reply_to.name' => ['nullable', 'string', 'max:150'],
            'sender' => ['nullable', 'array'],
            'sender.email' => ['required_with:sender', 'email:rfc'],
            'sender.name' => ['nullable', 'string', 'max:150'],
            'sender_id' => ['nullable', 'uuid'],
            'subject' => ['nullable', 'string', 'max:998'],
            'html_content' => ['nullable', 'string'],
            'text_content' => ['nullable', 'string'],
            'template_id' => ['nullable', 'uuid'],
            'params' => ['nullable', 'array'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:100'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:998'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
            'attachments.*.content' => ['nullable', 'string'],
            'attachments.*.url' => ['nullable', 'url'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
