<?php

namespace App\Modules\Recipients\Attributes\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * docs/45 §45.5.1 "Value encoding": text → string, number → number, date → "YYYY-MM-DD",
 * boolean → bool, category → option value, multiple_choice → string[] of option values,
 * user → user uuid. null / "" clear the value (returns null).
 */
class AttributeValueCaster
{
    /** @throws InvalidArgumentException French message */
    public function cast(ContactAttribute $attribute, mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($attribute->type) {
            'text' => $this->text($value),
            'number' => $this->number($value),
            'date' => $this->date($value),
            'boolean' => $this->boolean($value),
            'category' => $this->category($attribute, $value),
            'multiple_choice' => $this->multiple($attribute, $value),
            'user' => $this->user($value),
            default => throw new InvalidArgumentException('Type d\'attribut inconnu.'),
        };
    }

    private function text(mixed $value): string
    {
        if (! is_scalar($value)) {
            throw new InvalidArgumentException('La valeur doit être un texte.');
        }
        $text = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        if (mb_strlen($text) > 5000) {
            throw new InvalidArgumentException('Le texte ne doit pas dépasser 5000 caractères.');
        }

        return $text;
    }

    private function number(mixed $value): int|float
    {
        if (is_bool($value) || ! is_numeric($value)) {
            throw new InvalidArgumentException('La valeur doit être un nombre.');
        }
        $number = $value + 0;
        if (is_float($number) && ! is_finite($number)) {
            throw new InvalidArgumentException('La valeur doit être un nombre.');
        }

        return $number;
    }

    private function date(mixed $value): string
    {
        if (is_string($value)) {
            $input = trim($value);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ].*)?$/', $input, $m) === 1) {
                $input = $m[1];
                $format = 'Y-m-d';
            } else {
                $format = 'd/m/Y';
            }
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $input);
            } catch (Throwable) {
                $parsed = false;
            }
            if ($parsed !== false && $parsed->format($format) === $input) {
                return $parsed->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException('La valeur doit être une date au format AAAA-MM-JJ.');
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalised = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return match ($normalised) {
            '1', 'true', 'oui', 'yes' => true,
            '0', 'false', 'non', 'no' => false,
            default => throw new InvalidArgumentException('La valeur doit être vrai ou faux.'),
        };
    }

    private function category(ContactAttribute $attribute, mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('La valeur doit être une des options définies.');
        }
        $value = (string) $value;
        if (! in_array($value, $attribute->optionValues(), true)) {
            throw new InvalidArgumentException('La valeur doit être une des options définies.');
        }

        return $value;
    }

    /** @return list<string> */
    private function multiple(ContactAttribute $attribute, mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('La valeur doit être une liste d\'options.');
        }
        $allowed = $attribute->optionValues();
        $out = [];
        foreach ($value as $item) {
            if ((! is_string($item) && ! is_int($item)) || ! in_array((string) $item, $allowed, true)) {
                throw new InvalidArgumentException('Chaque valeur doit être une des options définies.');
            }
            $out[(string) $item] = (string) $item;
        }

        return array_values($out);
    }

    private function user(mixed $value): string
    {
        if (! is_string($value) || ! User::query()->where('uuid', $value)->exists()) {
            throw new InvalidArgumentException('La valeur doit être l\'identifiant d\'un utilisateur existant.');
        }

        return $value;
    }
}
