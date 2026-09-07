<?php

namespace Database\Factories;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::query()->where('name', RoleName::Viewer->value)->value('id')
                ?? Role::factory(),
            'is_active' => true,
            'remember_token' => Str::random(10),
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
            'two_factor_confirmed_at' => now(),
        ];
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withRole(RoleName $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::query()->where('name', $role->value)->value('id')
                ?? Role::factory()->state(['name' => $role->value]),
        ]);
    }

    /**
     * A confirmed TOTP enrolment. The default secret is a fixed, valid Base32
     * string so tests can derive live codes from it deterministically.
     *
     * @param  list<string>  $recoveryCodes
     */
    public function withTwoFactor(
        string $secret = 'ABCDEFGHIJKLMNOP',
        array $recoveryCodes = ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
    ): static {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
