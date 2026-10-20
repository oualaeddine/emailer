<?php

namespace Database\Seeders;

use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use Illuminate\Database\Seeder;

/** docs/45 §45.5.1 / §45.8 Slot D — system (read-only) contact attributes. Idempotent. */
class ContactAttributeSeeder extends Seeder
{
    /** @var list<array{name: string, label: string, storage: string, column: ?string}> */
    public const SYSTEM = [
        ['name' => 'EMAIL', 'label' => 'Email', 'storage' => 'column', 'column' => 'email'],
        ['name' => 'PRENOM', 'label' => 'Prénom', 'storage' => 'column', 'column' => 'first_name'],
        ['name' => 'NOM', 'label' => 'Nom', 'storage' => 'column', 'column' => 'last_name'],
        ['name' => 'SOCIETE', 'label' => 'Société', 'storage' => 'column', 'column' => 'company_name'],
        ['name' => 'SMS', 'label' => 'SMS', 'storage' => 'custom_fields', 'column' => null],
        ['name' => 'EXT_ID', 'label' => 'Identifiant externe', 'storage' => 'column', 'column' => 'ext_id'],
    ];

    public function run(): void
    {
        foreach (self::SYSTEM as $position => $row) {
            ContactAttribute::query()->updateOrCreate(['name' => $row['name']], [
                'label' => $row['label'],
                'type' => 'text',
                'options' => null,
                'is_system' => true,
                'storage' => $row['storage'],
                'column' => $row['column'],
                'position' => $position,
            ]);
        }
    }
}
