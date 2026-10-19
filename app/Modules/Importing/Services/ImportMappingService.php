<?php

namespace App\Modules\Importing\Services;

use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Importing\Models\ImportJob;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * docs/45-brevo-api-contracts.md §45.5.7 — `PUT /import-jobs/{id}/mapping`:
 * exactly one column maps to EMAIL, every attribute must exist (or be created
 * inline through `create_attributes`), no attribute is mapped twice.
 */
class ImportMappingService
{
    public function __construct(private readonly ImportAttributeCatalog $catalog)
    {
    }

    /**
     * @param  array<string, mixed>  $input  validated {mapping, create_attributes?}
     * @return list<array{column: int, header: string, target: array<string, string>}>
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function save(ImportJob $job, array $input, User $user): array
    {
        $created = $input['create_attributes'] ?? [];

        if ($created !== []) {
            if (! $user->hasPermission(PermissionName::AttributesManage->value)) {
                throw new AuthorizationException('Vous ne pouvez pas créer d’attribut.');
            }
        }

        $known = array_merge($this->catalog->names(), array_column($created, 'name'));
        $normalised = [];
        $emailColumns = 0;
        $seen = [];
        $columns = [];

        foreach ($input['mapping'] as $i => $entry) {
            $target = $entry['target'];

            if (isset($columns[$entry['column']])) {
                throw ValidationException::withMessages([
                    "mapping.{$i}.column" => 'Une colonne ne peut être associée qu’une seule fois.',
                ]);
            }
            $columns[$entry['column']] = true;

            if ($target['type'] === 'attribute') {
                $attribute = $target['attribute'] ?? null;

                if (! is_string($attribute) || ! in_array($attribute, $known, true)) {
                    throw ValidationException::withMessages([
                        "mapping.{$i}.target.attribute" => 'Attribut inconnu : '.(is_string($attribute) ? $attribute : '').'.',
                    ]);
                }
                if (isset($seen[$attribute])) {
                    throw ValidationException::withMessages([
                        "mapping.{$i}.target.attribute" => "L’attribut {$attribute} est associé à plusieurs colonnes.",
                    ]);
                }
                $seen[$attribute] = true;

                if ($attribute === 'EMAIL') {
                    $emailColumns++;
                }

                $normalised[] = [
                    'column' => (int) $entry['column'],
                    'header' => (string) ($entry['header'] ?? ''),
                    'target' => ['type' => 'attribute', 'attribute' => $attribute],
                ];
            } else {
                $normalised[] = [
                    'column' => (int) $entry['column'],
                    'header' => (string) ($entry['header'] ?? ''),
                    'target' => ['type' => 'ignore'],
                ];
            }
        }

        if ($emailColumns !== 1) {
            throw ValidationException::withMessages([
                'mapping' => 'Une colonne (et une seule) doit correspondre à l’attribut EMAIL.',
            ]);
        }

        $this->catalog->create($created, $user);

        $job->update([
            'mapping' => $normalised,
            'status' => 'pending',
        ]);

        return $normalised;
    }
}
