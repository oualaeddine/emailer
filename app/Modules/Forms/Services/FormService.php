<?php

namespace App\Modules\Forms\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** docs/45 §45.7.4 — forms CRUD, publish and share snippets. */
class FormService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public static function defaultGdpr(): array
    {
        return ['enabled' => false, 'text' => null, 'required' => true];
    }

    /** @return array<string, mixed> */
    public static function defaultDesign(): array
    {
        return [
            'logo_url' => null, 'primary_color' => '#0F6CBD', 'background_color' => '#F5F5F5', 'text_color' => '#242424',
            'font_family' => 'Segoe UI, sans-serif', 'button_text_color' => '#FFFFFF', 'border_radius' => 4,
            'button_text' => "S'inscrire", 'title' => 'Inscrivez-vous à notre newsletter', 'description' => null, 'layout' => 'vertical',
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultSettings(): array
    {
        return [
            'double_opt_in' => true, 'doi_template_id' => null, 'doi_redirect_url' => null,
            'success_message' => 'Merci ! Votre inscription a bien été prise en compte.', 'redirect_url' => null,
            'captcha_enabled' => false, 'update_existing' => true, 'notify_emails' => [],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultField(): array
    {
        return ['attribute' => 'EMAIL', 'label' => 'Email', 'placeholder' => null, 'required' => true, 'help_text' => null];
    }

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = Form::query();
        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%'.addcslashes((string) $filters['q'], '%_\\').'%');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(string $uuid): Form
    {
        return Form::query()->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException('Formulaire introuvable.');
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, ?User $actor): Form
    {
        $data = $this->normalise($data, null);
        $form = Form::query()->create($data + [
            'status' => Form::DRAFT, 'created_by' => $actor?->id,
        ]);
        $this->audit->record('form.created', $form, $actor, null, ['name' => $form->name]);

        return $form;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Form $form, array $data, ?User $actor): Form
    {
        $form->update($this->normalise($data, $form));
        $this->audit->record('form.updated', $form, $actor, null, ['name' => $form->name]);

        return $form->refresh();
    }

    public function delete(Form $form, ?User $actor): void
    {
        $this->audit->record('form.deleted', $form, $actor, ['name' => $form->name]);
        $form->delete();
    }

    public function duplicate(Form $form, ?User $actor): Form
    {
        $copy = Form::query()->create([
            'name' => mb_substr($form->name.' (copie)', 0, 150),
            'status' => Form::DRAFT,
            'list_ids' => $form->list_ids, 'fields' => $form->fields, 'gdpr' => $form->gdpr,
            'design' => $form->design, 'settings' => $form->settings, 'doi_template_id' => $form->doi_template_id,
            'created_by' => $actor?->id,
        ]);
        $this->audit->record('form.duplicated', $copy, $actor, null, ['source' => $form->uuid]);

        return $copy;
    }

    public function publish(Form $form, ?User $actor): Form
    {
        $errors = [];
        if (RecipientList::query()->whereIn('uuid', $form->list_ids ?? [])->doesntExist()) {
            $errors['list_ids'] = ['Sélectionnez au moins une liste avant de publier.'];
        }
        if (! collect($form->fields)->contains(fn ($f) => strtoupper((string) ($f['attribute'] ?? '')) === 'EMAIL')) {
            $errors['fields'] = ['Le formulaire doit contenir le champ EMAIL.'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $form->update(['status' => Form::ACTIVE]);
        $this->audit->record('form.published', $form, $actor, null, ['name' => $form->name]);

        return $form;
    }

    public function unpublish(Form $form, ?User $actor): Form
    {
        $form->update(['status' => Form::INACTIVE]);
        $this->audit->record('form.unpublished', $form, $actor, null, ['name' => $form->name]);

        return $form;
    }

    /** @return array{hosted_url: string, iframe_code: string, embed_html: string} */
    public function share(Form $form): array
    {
        $url = url('/f/'.$form->uuid);

        return [
            'hosted_url' => $url,
            'iframe_code' => '<iframe src="'.$url.'" width="100%" height="600" frameborder="0" style="border:0" title="'.e($form->name).'"></iframe>',
            'embed_html' => '<div data-pjm-form="'.$form->uuid.'"></div><script src="'.$url.'/embed.js" async></script>',
        ];
    }

    /**
     * Merges a (partial) input over the stored form and resolves the DOI template FK.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data, ?Form $form): array
    {
        $out = [];
        if (array_key_exists('name', $data)) {
            $out['name'] = $data['name'];
        }
        if (array_key_exists('list_ids', $data)) {
            $out['list_ids'] = array_values(array_unique($data['list_ids']));
        } elseif ($form === null) {
            $out['list_ids'] = [];
        }
        if (array_key_exists('fields', $data)) {
            $out['fields'] = array_map(fn (array $f) => [
                'attribute' => strtoupper($f['attribute']),
                'label' => $f['label'] ?? $f['attribute'],
                'placeholder' => $f['placeholder'] ?? null,
                'required' => (bool) ($f['required'] ?? false),
                'help_text' => $f['help_text'] ?? null,
            ], array_values($data['fields']));
        } elseif ($form === null) {
            $out['fields'] = [self::defaultField()];
        }
        foreach (['gdpr' => self::defaultGdpr(), 'design' => self::defaultDesign(), 'settings' => self::defaultSettings()] as $key => $defaults) {
            $base = $form?->{$key} ?? $defaults;
            if (array_key_exists($key, $data) || $form === null) {
                $out[$key] = array_merge($base, $data[$key] ?? []);
            }
        }
        if (isset($out['settings'])) {
            $uuid = $out['settings']['doi_template_id'] ?? null;
            $out['doi_template_id'] = $uuid ? DB::table('templates')->where('uuid', $uuid)->value('id') : null;
        }

        return $out;
    }
}
