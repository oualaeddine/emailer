<?php

namespace App\Modules\Recipients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.4 — POST /api/v1/recipients/{uuid}/notes.
 * Permission (`recipients.annotate`) checked in the controller via Gate,
 * since it does not depend on request input.
 */
class StoreRecipientNoteRequest extends FormRequest
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
        return ['body' => ['required', 'string', 'max:2000']];
    }
}
