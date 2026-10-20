<?php

namespace Database\Factories;

use App\Modules\Senders\Models\SendingDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SendingDomain>
 */
class SendingDomainFactory extends Factory
{
    protected $model = SendingDomain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'status' => SendingDomain::STATUS_PENDING,
            'dkim_selector' => 'pjm1',
            'dkim_private_key' => null,
            'dkim_public_key' => null,
            'records' => [
                ['kind' => 'spf', 'host' => $domain, 'type' => 'TXT', 'value' => 'v=spf1 include:_spf.example.test ~all', 'status' => 'pending', 'found_value' => null, 'checked_at' => null],
                ['kind' => 'dkim', 'host' => 'pjm1._domainkey.'.$domain, 'type' => 'TXT', 'value' => 'v=DKIM1; k=rsa; p=AAAA', 'status' => 'pending', 'found_value' => null, 'checked_at' => null],
                ['kind' => 'dmarc', 'host' => '_dmarc.'.$domain, 'type' => 'TXT', 'value' => 'v=DMARC1; p=none; rua=mailto:dmarc@'.$domain, 'status' => 'pending', 'found_value' => null, 'checked_at' => null],
            ],
            'last_checked_at' => null,
            'created_by' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(function (array $attributes): array {
            $records = array_map(fn (array $r): array => [...$r, 'status' => 'valid'], $attributes['records']);

            return ['status' => SendingDomain::STATUS_VERIFIED, 'records' => $records, 'last_checked_at' => now()];
        });
    }
}
