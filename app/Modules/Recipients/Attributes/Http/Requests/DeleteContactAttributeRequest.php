<?php

namespace App\Modules\Recipients\Attributes\Http\Requests;

use App\Modules\Recipients\Attributes\Policies\ContactAttributePolicy;
use Illuminate\Foundation\Http\FormRequest;

/** docs/45 §45.5.1 — DELETE body `{ "confirm": true }`. */
class DeleteContactAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ContactAttributePolicy::class)->manage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirm' => ['required', 'accepted']];
    }
}
