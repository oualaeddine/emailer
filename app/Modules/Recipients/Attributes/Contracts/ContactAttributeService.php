<?php

namespace App\Modules\Recipients\Attributes\Contracts;

use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Attributes\Services\ContactAttributeService as Implementation;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Support\Collection;

/** docs/45 §45.9 — the single writer of attribute values. */
#[Bind(Implementation::class)]
interface ContactAttributeService
{
    /** @return Collection<string, ContactAttribute> keyed by UPPERCASE name */
    public function definitions(): Collection;

    /**
     * Validates, casts and saves `$values` (NAME => raw value) on the recipient and fires
     * ContactAttributesUpdated. Throws ValidationException keyed `attributes.NAME`.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}> changes
     */
    public function apply(Recipient $recipient, array $values, string $source): array;

    /** Validates and casts one raw value for storage; throws ValidationException (`attributes.NAME`). */
    public function castForStorage(ContactAttribute $attribute, mixed $value): mixed;
}
