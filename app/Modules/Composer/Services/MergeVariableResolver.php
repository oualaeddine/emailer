<?php

namespace App\Modules\Composer\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Models\Recipient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * docs/11-email-composer.md §11.4 — Merge Variables, extended by docs/43 §43.6.6 / docs/45 §45.5.1.
 * Syntax: `{{recipient.first_name}}`, `{{recipient.custom_fields.xxx}}`, system variables
 * `{{unsubscribe_url}}`/`{{unsubscribe}}`/`{{current_date}}`, `{{ contact.NAME }}` for every contact
 * attribute definition (case-insensitive), with fallback `{{recipient.first_name|there}}` or
 * `{{ contact.PRENOM | default:"cher client" }}`.
 */
class MergeVariableResolver
{
    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_.]+)\s*(?:\|\s*([^}]*?))?\s*\}\}/';

    /** @var Collection<string, ContactAttribute>|null */
    private ?Collection $definitions = null;

    private ?int $definitionsLoadedAt = null;

    public function __construct(private readonly ?ContactAttributeService $attributes = null) {}

    public function resolve(string $content, Recipient $recipient, ?string $unsubscribeUrl = null): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            function (array $matches) use ($recipient, $unsubscribeUrl): string {
                $value = $this->resolvePath($matches[1], $recipient, $unsubscribeUrl);

                return $value !== null && $value !== '' ? $value : $this->fallback($matches[2] ?? '');
            },
            $content,
        );
    }

    /** `default:"x"` / `default:'x'` / legacy bare text. */
    private function fallback(string $filter): string
    {
        $filter = trim($filter);

        if (preg_match('/^default\s*:\s*(?:"(.*)"|\'(.*)\'|(.*))$/s', $filter, $m) === 1) {
            foreach ([1, 2, 3] as $i) {
                if (isset($m[$i]) && $m[$i] !== '') {
                    return $m[$i];
                }
            }

            return '';
        }

        return $filter;
    }

    private function resolvePath(string $path, Recipient $recipient, ?string $unsubscribeUrl): ?string
    {
        if ($path === 'unsubscribe_url' || $path === 'unsubscribe') {
            return $unsubscribeUrl;
        }

        if ($path === 'current_date') {
            return now()->translatedFormat('d/m/Y');
        }

        if (str_starts_with($path, 'recipient.custom_fields.')) {
            $key = substr($path, strlen('recipient.custom_fields.'));

            return isset($recipient->custom_fields[$key]) ? $this->stringify($recipient->custom_fields[$key]) : null;
        }

        if (str_starts_with(strtolower($path), 'contact.')) {
            return $this->resolveContact(substr($path, 8), $recipient);
        }

        return match ($path) {
            'recipient.first_name' => $recipient->first_name,
            'recipient.last_name' => $recipient->last_name,
            'recipient.email' => $recipient->email,
            'recipient.company_name' => $recipient->company_name,
            default => null,
        };
    }

    private function resolveContact(string $name, Recipient $recipient): ?string
    {
        $upper = strtoupper($name);
        $definition = $this->definitions()->get($upper);

        if ($definition !== null) {
            if ($definition->storage === 'column' && $definition->column !== null) {
                $raw = $recipient->getAttribute($definition->column);
            } else {
                $raw = $this->customField($recipient, $definition->name);
            }

            return $this->format($definition, $raw);
        }

        // Wave 1 aliases (work even without definitions) …
        $alias = match ($upper) {
            'PRENOM' => $recipient->first_name,
            'NOM' => $recipient->last_name,
            'EMAIL' => $recipient->email,
            'SOCIETE' => $recipient->company_name,
            default => false,
        };
        if ($alias !== false) {
            return $alias;
        }

        // … then legacy free-form custom fields, case-insensitively.
        $raw = $this->customField($recipient, $name);

        return $raw === null ? null : $this->stringify($raw);
    }

    private function customField(Recipient $recipient, string $name): mixed
    {
        foreach ($recipient->custom_fields ?? [] as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function format(ContactAttribute $definition, mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        $labels = collect($definition->options ?? [])->mapWithKeys(fn ($o) => [(string) $o['value'] => $o['label'] ?? $o['value']]);

        return match ($definition->type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? 'Oui' : 'Non',
            'date' => $this->formatDate($raw),
            'category' => $labels->get((string) $raw, (string) $raw),
            'multiple_choice' => collect((array) $raw)->map(fn ($v) => $labels->get((string) $v, (string) $v))->implode(', '),
            'user' => User::query()->where('uuid', (string) $raw)->value('name') ?? null,
            default => $this->stringify($raw),
        };
    }

    private function formatDate(mixed $raw): string
    {
        try {
            return Carbon::parse((string) $raw)->format('d/m/Y');
        } catch (Throwable) {
            return (string) $raw;
        }
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Oui' : 'Non',
            is_array($value) => implode(', ', array_map('strval', $value)),
            default => (string) $value,
        };
    }

    /** @return Collection<string, ContactAttribute> */
    private function definitions(): Collection
    {
        if ($this->definitions === null || time() - ($this->definitionsLoadedAt ?? 0) > 30) {
            try {
                $service = $this->attributes ?? app(ContactAttributeService::class);
                $this->definitions = $service->definitions();
            } catch (Throwable) {
                $this->definitions = new Collection;
            }
            $this->definitionsLoadedAt = time();
        }

        return $this->definitions;
    }

    /**
     * @return list<string>
     */
    public function extractVariableNames(string $content): array
    {
        preg_match_all(self::PATTERN, $content, $matches);

        return array_values(array_unique($matches[1]));
    }
}
