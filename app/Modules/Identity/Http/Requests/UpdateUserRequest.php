<?php

namespace App\Modules\Identity\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * docs/29-api-specification.md §29.2 — PATCH /api/v1/users/{uuid} —
 * "update role/active status".
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::UsersManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
            // Optional administrator reset; empty = unchanged.
            'password' => [
                'nullable',
                'string',
                'confirmed',
                Password::min(12)->mixedCase()->numbers(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // An administrator may not bypass the current-password check on themselves.
                    if ($value !== null && $value !== '' && $this->route('user')?->is($this->user())) {
                        $fail('Pour changer votre propre mot de passe, utilisez « Changer le mot de passe » dans le menu du compte.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 12 caractères.',
            'password.mixed' => 'Le mot de passe doit contenir des majuscules et des minuscules.',
            'password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
            'role_id.exists' => "Le rôle sélectionné n'existe pas.",
        ];
    }
}
