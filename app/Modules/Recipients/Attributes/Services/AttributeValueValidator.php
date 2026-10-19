<?php

namespace App\Modules\Recipients\Attributes\Services;

use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Validates + casts a map of NAME => raw value against definitions; errors keyed `attributes.NAME`. */
class AttributeValueValidator
{
    public function __construct(private readonly AttributeValueCaster $caster) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  Collection<string, ContactAttribute>  $definitions  keyed by UPPERCASE name
     * @param  bool  $allowUnknown  keep keys without a definition untouched (legacy custom_fields)
     * @param  callable(ContactAttribute): bool|null  $only  restrict validation to matching definitions
     * @return array<string, mixed> cast values keyed by canonical attribute name (null = cleared)
     *
     * @throws ValidationException
     */
    public function validate(array $values, Collection $definitions, bool $allowUnknown = false, ?callable $only = null): array
    {
        $errors = [];
        $out = [];

        foreach ($values as $key => $raw) {
            $definition = $definitions->get(strtoupper((string) $key));

            if ($definition === null || ($only !== null && ! $only($definition))) {
                if ($definition === null && ! $allowUnknown) {
                    $errors['attributes.'.strtoupper((string) $key)] = ['Attribut inconnu.'];
                } else {
                    $out[$key] = $raw;
                }

                continue;
            }

            try {
                $out[$definition->name] = $this->caster->cast($definition, $raw);
            } catch (InvalidArgumentException $e) {
                $errors['attributes.'.$definition->name] = [$e->getMessage()];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }
}
