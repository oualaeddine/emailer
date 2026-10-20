<?php

namespace App\Modules\Suppression\Preferences\Services;

use App\Domain\Enums\SuppressionReason;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Lists\Services\ListMembershipService;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Preferences\Events\ContactPreferencesUpdated;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use App\Modules\Suppression\Services\SuppressionManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * docs/45 §45.6.6 — CRUD of unsubscribe pages / update-profile forms and the
 * per-list preference changes made from the public page.
 */
class PreferenceService
{
    public const SOURCE = 'preferences';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ListMembershipService $memberships,
        private readonly SuppressionManager $suppression,
    ) {}

    // ---- CRUD -------------------------------------------------------------

    /** @return Collection<int, UnsubscribePage>|Collection<int, UpdateProfileForm> */
    public function list(string $modelClass): Collection
    {
        return $modelClass::query()->orderByDesc('is_default')->orderBy('name')->get();
    }

    /** @param class-string<UnsubscribePage|UpdateProfileForm> $modelClass */
    public function findByUuid(string $modelClass, string $uuid): Model
    {
        return $modelClass::query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPage(array $data, ?User $actor): UnsubscribePage
    {
        $page = UnsubscribePage::query()->create([
            'name' => $data['name'],
            'content' => array_merge(PageDefaults::unsubscribeContent(), $data['content'] ?? []),
            'design' => array_merge(PageDefaults::design(), $data['design'] ?? []),
            'is_default' => ! UnsubscribePage::query()->where('is_default', true)->exists(),
            'created_by' => $actor?->id,
        ]);
        $this->audit->record('unsubscribe_page.updated', $page, $actor, null, ['name' => $page->name]);

        return $page;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForm(array $data, ?User $actor): UpdateProfileForm
    {
        return UpdateProfileForm::query()->create([
            'name' => $data['name'],
            'fields' => $data['fields'] ?? [],
            'list_ids' => array_values($data['list_ids'] ?? []),
            'content' => array_merge(PageDefaults::profileContent(), $data['content'] ?? []),
            'design' => array_merge(PageDefaults::design(), $data['design'] ?? []),
            'is_default' => ! UpdateProfileForm::query()->where('is_default', true)->exists(),
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  partial input
     */
    public function update(Model $record, array $data, ?User $actor = null): Model
    {
        foreach (['content', 'design'] as $json) {
            if (isset($data[$json])) {
                $data[$json] = array_merge($record->{$json} ?? [], $data[$json]);
            }
        }
        if (isset($data['list_ids'])) {
            $data['list_ids'] = array_values($data['list_ids']);
        }
        $record->update($data);
        if ($record instanceof UnsubscribePage) {
            $this->audit->record('unsubscribe_page.updated', $record, $actor, null, ['name' => $record->name]);
        }

        return $record;
    }

    public function delete(Model $record): void
    {
        if ($record->is_default) {
            throw new ConflictHttpException('La page par défaut ne peut pas être supprimée.');
        }
        $column = $record instanceof UnsubscribePage ? 'unsubscribe_page_id' : 'update_profile_form_id';
        $inUse = DB::table('campaigns')
            ->where($column, $record->id)
            ->whereIn('status', ['scheduled', 'sending'])
            ->exists();
        if ($inUse) {
            throw new ConflictHttpException('Cette page est utilisée par une campagne planifiée ou en cours d’envoi.');
        }
        $record->delete();
    }

    public function setDefault(Model $record): Model
    {
        DB::transaction(function () use ($record): void {
            $record::query()->where('is_default', true)->update(['is_default' => false]);
            $record->forceFill(['is_default' => true])->save();
        });

        return $record->refresh();
    }

    public function defaultPage(): ?UnsubscribePage
    {
        return UnsubscribePage::query()->where('is_default', true)->first() ?? UnsubscribePage::query()->orderBy('id')->first();
    }

    public function defaultForm(): ?UpdateProfileForm
    {
        return UpdateProfileForm::query()->where('is_default', true)->first() ?? UpdateProfileForm::query()->orderBy('id')->first();
    }

    // ---- Public page ------------------------------------------------------

    /**
     * Lists offered on the page: the form's `list_ids`, or (when the form pins
     * none) every list the contact currently belongs to. `subscribed` = member.
     *
     * @return list<array{id: string, name: string, subscribed: bool}>
     */
    public function listsFor(Recipient $recipient, ?UpdateProfileForm $form): array
    {
        $memberOf = DB::table('recipient_list_recipient')->where('recipient_id', $recipient->id)->pluck('recipient_list_id')->all();
        $pinned = $form?->list_ids ?? [];

        $query = RecipientList::query()->orderBy('name');
        if ($pinned !== []) {
            $query->whereIn('uuid', $pinned);
        } else {
            $query->whereIn('id', $memberOf ?: [0]);
        }

        return $query->get()->map(fn (RecipientList $l): array => [
            'id' => $l->uuid,
            'name' => $l->name,
            'subscribed' => in_array($l->id, $memberOf, true),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $attributes  NAME => value (only the form's fields are applied)
     * @param  list<string>  $keptListUuids  lists the contact keeps / joins
     */
    public function apply(Recipient $recipient, ?UpdateProfileForm $form, array $attributes, array $keptListUuids, bool $unsubscribeAll): void
    {
        $removed = [];
        $added = [];

        if ($unsubscribeAll) {
            $this->suppression->suppress($recipient->email, SuppressionReason::GlobalUnsubscribe);
            $memberOf = DB::table('recipient_list_recipient')->where('recipient_id', $recipient->id)->pluck('recipient_list_id')->all();
            foreach (RecipientList::query()->whereIn('id', $memberOf)->get() as $list) {
                $this->memberships->remove($list, [$recipient->id], self::SOURCE);
                $removed[] = $list->id;
            }
        } else {
            $this->applyAttributes($recipient, $form, $attributes);
            foreach ($this->listsFor($recipient, $form) as $offered) {
                $list = RecipientList::query()->where('uuid', $offered['id'])->first();
                if ($list === null) {
                    continue;
                }
                $keep = in_array($offered['id'], $keptListUuids, true);
                if ($offered['subscribed'] && ! $keep) {
                    $this->memberships->remove($list, [$recipient->id], self::SOURCE);
                    $removed[] = $list->id;
                } elseif (! $offered['subscribed'] && $keep && $recipient->status !== 'suppressed') {
                    $this->memberships->add($list, [$recipient->id], self::SOURCE);
                    $added[] = $list->id;
                }
            }
        }

        ContactPreferencesUpdated::dispatch($recipient->id, $removed, $added, $unsubscribeAll);
    }

    /** @param  array<string, mixed>  $attributes */
    private function applyAttributes(Recipient $recipient, ?UpdateProfileForm $form, array $attributes): void
    {
        $allowed = collect($form?->fields ?? [])->pluck('attribute')->all();
        $values = array_intersect_key($attributes, array_flip($allowed));
        if ($values === []) {
            return;
        }
        app(ContactAttributeService::class)->apply($recipient, $values, self::SOURCE);
    }
}
