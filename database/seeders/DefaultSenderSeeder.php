<?php

namespace Database\Seeders;

use App\Modules\Senders\Models\Sender;
use App\Modules\Senders\Models\SendingDomain;
use Illuminate\Database\Seeder;

/**
 * docs/45 §45.4.1 — default sender seeded from MAIL_FROM_ADDRESS / MAIL_FROM_NAME
 * (`verification_method = "system"`). Idempotent.
 */
class DefaultSenderSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) config('mail.from.address'));
        if ($email === '') {
            return;
        }

        $sender = Sender::withTrashed()->where('email', $email)->first();
        if ($sender !== null) {
            return;
        }

        Sender::query()->create([
            'name' => (string) config('mail.from.name'),
            'email' => $email,
            'domain_id' => SendingDomain::query()->where('domain', Sender::domainOf($email))->value('id'),
            'is_default' => ! Sender::query()->where('is_default', true)->exists(),
            'verification_method' => 'system',
            'verified_at' => now(),
        ]);
    }
}
