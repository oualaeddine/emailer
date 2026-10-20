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
 *
 * Extra variables (docs/45 §45.5.1 MergeTag groups `links` / `campaign`, §45.6.3) are passed by
 * the rendering pipeline as `$variables`, keyed by lower-cased path: `mirror`, `update_profile`,
 * `campaign.name`, `campaign.subject`, `sender.name`, `sender.email`. Unknown or empty variables
 * fall back to the filter default (or to an empty string), so `{{ mirror }}` renders `""` when the
 * caller supplies no mirror URL.
 */
class MergeVariableResolver
{
    /** Extra variables supplied by the rendering pipeline (MergeTagsStage), lower-cased. */
    public const EXTRA_VARIABLES = ['mirror', 'update_profile', 'campaign.name', 'campaign.subject', 'sender.name', 'sender.email'];

    private const SYSTEM_PATHS = ['unsubscribe_url', 'unsubscribe', 'current_date'];

    private const RECIPIENT_PATHS = ['recipient.first_name', 'recipient.last_name', 'recipient.email', 'recipient.company_name'];

    private const CONTACT_ALIASES = ['PRENOM', 'NOM', 'EMAIL', 'SOCIETE'];

    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_.]+)\s*(?:\|\s*([^}]*?))?\s*\}\}/';

    /** @var Collection<string, ContactAttribute>|null */
    private ?Collection $definitions = null;

    private ?int $definitionsLoadedAt = null;

    public function __construct(private readonly ?ContactAttributeService $attributes = null) {}

    /**
     * @param  array<string, string|null>  $variables  extra variables keyed by path (case-insensitive)
     */
    public function resolve(string $content, Recipient $recipient, ?string $unsubscribeUrl = null, array $variables = []): string
    {
        return $this->replace($content, $recipient, $unsubscribeUrl, $variables, false);
    }

    /**
     * Same as {@see resolve()} for an HTML body: resolved values are
     * HTML-escaped (contact data can never inject markup); author-written
     * `default:` fallbacks are inserted as written.
     *
     * @param  array<string, string|null>  $variables
     */
    public function resolveHtml(string $content, Recipient $recipient, ?string $unsubscribeUrl = null, array $variables = []): string
    {
        return $this->replace($content, $recipient, $unsubscribeUrl, $variables, true);
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    private function replace(string $content, Recipient $recipient, ?string $unsubscribeUrl, array $variables, bool $escape): string
    {
        $variables = array_change_key_case($variables, CASE_LOWER);

        return (string) preg_replace_callback(
            self::PATTERN,
            function (array $matches) use ($recipient, $unsubscribeUrl, $variables, $escape): string {
                $key = strtolower($matches[1]);
                $value = array_key_exists($key, $variables)
                    ? $variables[$key]
                    : $this->resolvePath($matches[1], $recipient, $unsubscribeUrl);

                if ($value === null || $value === '') {
                    return $this->fallback($matches[2] ?? '');
                }

                return $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) : $value;
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
     * Merge tags (as written, de-duplicated) that the renderer would not substitute: same PATTERN
     * and same known-variable logic as {@see resolvePath()}. `{{ params.* }}` is transactional-only,
     * so it is unknown here. `{{ contact.X }}` is known when X is an attribute definition or a
     * built-in alias; free-form legacy custom fields cannot be verified statically.
     *
     * @return list<string>
     */
    public function unknownTags(string ...$contents): array
    {
        $unknown = [];

        foreach ($contents as $content) {
            if ($content === '') {
                continue;
            }
            preg_match_all(self::PATTERN, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                if (! $this->isKnownPath($m[1])) {
                    $unknown[$m[0]] = true;
                }
            }
        }

        return array_map('strval', array_keys($unknown));
    }

    private function isKnownPath(string $path): bool
    {
        if (in_array(strtolower($path), self::EXTRA_VARIABLES, true)
            || in_array($path, self::SYSTEM_PATHS, true)
            || in_array($path, self::RECIPIENT_PATHS, true)) {
            return true;
        }

        if (str_starts_with($path, 'recipient.custom_fields.')) {
            return strlen($path) > strlen('recipient.custom_fields.');
        }

        if (str_starts_with(strtolower($path), 'contact.')) {
            $upper = strtoupper(substr($path, 8));

            return $upper !== '' && (in_array($upper, self::CONTACT_ALIASES, true) || $this->definitions()->has($upper));
        }

        return false;
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
