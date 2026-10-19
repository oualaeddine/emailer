<?php

namespace App\Modules\Importing\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * docs/45-brevo-api-contracts.md §45.5.1 — the attribute names an import may
 * target. System attributes are static; custom ones come from WP-50's
 * `contact_attributes` table when it exists (Schema guard until WP-50 merges).
 */
class ImportAttributeCatalog
{
    /** @var array<string, string> system attribute => recipients column / pseudo field */
    public const SYSTEM = [
        'EMAIL' => 'email',
        'PRENOM' => 'first_name',
        'NOM' => 'last_name',
        'SOCIETE' => 'company_name',
        'SMS' => 'custom:SMS',
        'EXT_ID' => 'ext_id',
    ];

    public const NAME_PATTERN = '/^[A-Z][A-Z0-9_]{0,49}$/';

    /** @var list<string> */
    private const TYPES = ['text', 'number', 'date', 'boolean', 'category', 'multiple_choice', 'user'];

    public function hasDefinitionsTable(): bool
    {
        return Schema::hasTable('contact_attributes');
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys(self::SYSTEM);

        if ($this->hasDefinitionsTable()) {
            $names = array_merge($names, DB::table('contact_attributes')->pluck('name')->all());
        }

        return array_values(array_unique($names));
    }

    public function isSystem(string $name): bool
    {
        return isset(self::SYSTEM[$name]);
    }

    /**
     * Header-based guess for the "suggested_mapping" of the preview.
     */
    public function guess(string $header): ?string
    {
        $key = Str::of(Str::ascii($header))->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();

        $synonyms = [
            'EMAIL' => ['email', 'mail', 'courriel', 'emailaddress', 'adresseemail', 'adressemail'],
            'PRENOM' => ['prenom', 'firstname', 'givenname'],
            'NOM' => ['nom', 'lastname', 'surname', 'nomdefamille'],
            'SOCIETE' => ['societe', 'entreprise', 'company', 'companyname', 'raisonsociale'],
            'SMS' => ['sms', 'telephone', 'tel', 'mobile', 'phone'],
            'EXT_ID' => ['extid', 'externalid'],
        ];

        foreach ($synonyms as $attribute => $keys) {
            if (in_array($key, $keys, true)) {
                return $attribute;
            }
        }

        $upper = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii(trim($header))) ?? '');
        if ($upper !== '' && in_array($upper, $this->names(), true)) {
            return $upper;
        }

        return null;
    }

    /**
     * @param  list<array{name: string, type: string}>  $definitions
     * @return list<string>  names that were accepted
     */
    public function create(array $definitions, ?User $creator): array
    {
        $accepted = [];

        foreach ($definitions as $definition) {
            $name = $definition['name'];

            if ($this->hasDefinitionsTable() && ! DB::table('contact_attributes')->where('name', $name)->exists()) {
                DB::table('contact_attributes')->insert([
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'label' => ucfirst(strtolower($name)),
                    'type' => $definition['type'],
                    'options' => null,
                    'is_system' => false,
                    'storage' => 'custom_fields',
                    'created_by' => $creator?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $accepted[] = $name;
        }

        return $accepted;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return self::TYPES;
    }
}
