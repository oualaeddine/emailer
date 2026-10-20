<?php

namespace App\Modules\Recipients\Attributes\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService as Contract;
use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;
use App\Modules\Recipients\Attributes\Exceptions\AttributeConflictException;
use App\Modules\Recipients\Attributes\Jobs\PurgeAttributeValuesJob;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** docs/45 §45.5.1 / §45.9 — contact attribute definitions and the single writer of values. */
class ContactAttributeService implements Contract
{
    public function __construct(
        private readonly AttributeValueCaster $caster,
        private readonly AttributeValueValidator $validator,
        private readonly AttributeUsageFinder $usage,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<string, ContactAttribute> */
    public function definitions(): Collection
    {
        return ContactAttribute::query()
            ->orderByDesc('is_system')->orderBy('position')->orderBy('name')
            ->get()->keyBy(fn (ContactAttribute $a) => strtoupper($a->name));
    }

    public function castForStorage(ContactAttribute $attribute, mixed $value): mixed
    {
        try {
            return $this->caster->cast($attribute, $value);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['attributes.'.$attribute->name => [$e->getMessage()]]);
        }
    }

    /**
     * Validates/casts the `custom_fields` of a recipient request: keys with a custom_fields-stored
     * definition are cast (errors keyed `attributes.NAME`); other keys pass through (legacy).
     *
     * @param  array<string, mixed>  $customFields
     * @return array<string, mixed>
     */
    public function castCustomFields(array $customFields): array
    {
        $cast = $this->validator->validate(
            $customFields, $this->definitions(), true, fn (ContactAttribute $a) => $a->storage === 'custom_fields',
        );

        return array_filter($cast, fn ($v) => $v !== null);
    }

    public function apply(Recipient $recipient, array $values, string $source): array
    {
        $definitions = $this->definitions();

        foreach ($values as $key => $_) {
            if (strtoupper((string) $key) === 'EMAIL') {
                throw ValidationException::withMessages(['attributes.EMAIL' => ['L\'attribut EMAIL ne peut pas être modifié.']]);
            }
        }

        $cast = $this->validator->validate($values, $definitions, false);
        $changes = [];
        $fields = $recipient->custom_fields ?? [];

        foreach ($cast as $name => $new) {
            $definition = $definitions->get(strtoupper($name));
            $useColumn = $definition->storage === 'column' && $this->hasColumn($definition->column);
            $old = $useColumn ? $recipient->getAttribute($definition->column) : ($fields[$definition->name] ?? null);

            if (json_encode($old) === json_encode($new)) {
                continue;
            }

            if ($useColumn) {
                $recipient->setAttribute($definition->column, $new);
            } elseif ($new === null) {
                unset($fields[$definition->name]);
            } else {
                $fields[$definition->name] = $new;
            }
            $changes[$definition->name] = ['old' => $old, 'new' => $new];
        }

        if ($changes !== []) {
            $recipient->custom_fields = $fields;
            $recipient->save();
            ContactAttributesUpdated::dispatch($recipient->id, $changes, $source);
        }

        return $changes;
    }

    /** @param array{name: string, label?: ?string, type: string, options?: ?array} $data */
    public function create(array $data, ?User $actor): ContactAttribute
    {
        $attribute = DB::transaction(function () use ($data, $actor): ContactAttribute {
            if (ContactAttribute::query()->lockForUpdate()->count() >= ContactAttribute::MAX_PER_ACCOUNT) {
                throw ValidationException::withMessages(['name' => ['Limite de '.ContactAttribute::MAX_PER_ACCOUNT.' attributs atteinte.']]);
            }

            $isChoice = in_array($data['type'], ['category', 'multiple_choice'], true);

            return ContactAttribute::query()->create([
                'name' => $data['name'],
                'label' => $data['label'] ?? $data['name'],
                'type' => $data['type'],
                'options' => $isChoice ? array_values($data['options'] ?? []) : null,
                'is_system' => false,
                'storage' => 'custom_fields',
                'position' => (int) ContactAttribute::query()->where('is_system', false)->max('position') + 1,
                'created_by' => $actor?->id,
            ]);
        });

        $this->audit->record('attribute.created', $attribute, $actor, null, [
            'name' => $attribute->name, 'type' => $attribute->type,
        ]);

        return $attribute;
    }

    /** @param array{label?: ?string, options?: ?array} $data */
    public function update(ContactAttribute $attribute, array $data): ContactAttribute
    {
        $this->assertNotSystem($attribute);

        if (array_key_exists('options', $data) && $attribute->isChoice()) {
            $new = array_values($data['options'] ?? []);
            $removed = array_diff($attribute->optionValues(), array_map(fn ($o) => (string) $o['value'], $new));
            foreach ($removed as $value) {
                if ($this->optionInUse($attribute, (string) $value)) {
                    throw new AttributeConflictException('L\'option « '.$value.' » est utilisée par des contacts et ne peut pas être supprimée.');
                }
            }
            $attribute->options = $new;
        }

        if (array_key_exists('label', $data)) {
            $attribute->label = $data['label'] ?: $attribute->name;
        }

        $attribute->save();

        return $attribute;
    }

    public function delete(ContactAttribute $attribute, ?User $actor): void
    {
        $this->assertNotSystem($attribute);

        $usedIn = $this->usage->find($attribute->name);
        if ($usedIn !== []) {
            throw new AttributeConflictException('Cet attribut est utilisé et ne peut pas être supprimé.', $usedIn);
        }

        $this->audit->record('attribute.deleted', $attribute, $actor, [
            'name' => $attribute->name, 'type' => $attribute->type,
        ]);
        $name = $attribute->name;
        $attribute->delete();
        PurgeAttributeValuesJob::dispatch($name);
    }

    public function usageCount(ContactAttribute $attribute): int
    {
        return $this->usingRecipients($attribute)->count();
    }

    /** @return Collection<int, array{tag: string, label: string, group: string, attribute: ?string}> */
    public function mergeTags(): Collection
    {
        $tags = $this->definitions()->map(fn (ContactAttribute $a) => [
            'tag' => '{{ contact.'.$a->name.' }}',
            'label' => $a->label ?: $a->name,
            'group' => 'contact',
            'attribute' => $a->name,
        ])->values();

        $static = [
            ['{{ mirror }}', 'Lien miroir', 'links'],
            ['{{ unsubscribe }}', 'Lien de désinscription', 'links'],
            ['{{ update_profile }}', 'Lien de mise à jour du profil', 'links'],
            ['{{ campaign.NAME }}', 'Nom de la campagne', 'campaign'],
            ['{{ campaign.SUBJECT }}', 'Objet de la campagne', 'campaign'],
            ['{{ sender.NAME }}', 'Nom de l\'expéditeur', 'campaign'],
            ['{{ sender.EMAIL }}', 'Email de l\'expéditeur', 'campaign'],
        ];

        foreach ($static as [$tag, $label, $group]) {
            $tags->push(['tag' => $tag, 'label' => $label, 'group' => $group, 'attribute' => null]);
        }

        return $tags;
    }

    private function assertNotSystem(ContactAttribute $attribute): void
    {
        if ($attribute->is_system) {
            throw new AttributeConflictException('Les attributs système ne peuvent pas être modifiés ni supprimés.');
        }
    }

    /** @return Builder<Recipient> */
    private function usingRecipients(ContactAttribute $attribute): Builder
    {
        $query = Recipient::query();

        if ($attribute->storage === 'column' && $this->hasColumn($attribute->column)) {
            return $query->whereNotNull($attribute->column);
        }

        return $query->whereNotNull('custom_fields->'.$attribute->name);
    }

    private function optionInUse(ContactAttribute $attribute, string $value): bool
    {
        $query = Recipient::query();

        return $attribute->type === 'multiple_choice'
            ? $query->whereJsonContains('custom_fields->'.$attribute->name, $value)->exists()
            : $query->where('custom_fields->'.$attribute->name, $value)->exists();
    }

    private function hasColumn(?string $column): bool
    {
        static $cache = [];

        return $column !== null && ($cache[$column] ??= Schema::hasColumn('recipients', $column));
    }
}
