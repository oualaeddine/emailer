<?php

namespace App\Modules\Recipients\Views\Services;

use App\Domain\Enums\RecipientStatus;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Events\RecipientDeleted;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Views\Contracts\ContactSelection;
use App\Modules\Recipients\Views\Jobs\BulkRecipientActionJob;
use App\Modules\Recipients\Views\Models\AsyncOperation;
use App\Modules\Recipients\Views\Support\ContactColumns;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Services\SuppressionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.5.4 — `POST /recipients/bulk`.
 */
class ContactBulkService
{
    public const ACTIONS = ['add_to_list', 'remove_from_list', 'blocklist', 'unblocklist', 'update_attribute', 'delete'];

    private const LIST_MEMBERSHIP = 'App\\Modules\\Recipients\\Lists\\Contracts\\ListMembershipService';

    private const ATTRIBUTE_SERVICE = 'App\\Modules\\Recipients\\Attributes\\Contracts\\ContactAttributeService';

    private const SKIPPED_CAP = 100;

    public function __construct(
        private readonly ContactQueryService $queries,
        private readonly AsyncOperationService $operations,
        private readonly SuppressionManager $suppression,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $params
     * @return array{result?: array<string, mixed>, operation?: AsyncOperation}
     */
    public function execute(User $user, string $action, array $selection, array $params): array
    {
        $this->assertParams($action, $params);
        $count = $this->queries->selectionQuery($selection, $user)->count();

        if ($count > ContactSelection::SYNC_LIMIT) {
            $operation = $this->operations->start('contacts_bulk', $user, [
                'action' => $action, 'selection' => $selection, 'params' => $params,
            ], $count);
            BulkRecipientActionJob::dispatch($operation->id);

            return ['operation' => $operation];
        }

        return ['result' => $this->process($user, $action, $selection, $params)];
    }

    public function runOperation(AsyncOperation $operation): void
    {
        $user = $operation->user_id !== null ? User::query()->find($operation->user_id) : null;
        $payload = $operation->payload ?? [];

        try {
            $this->operations->progress($operation, 0);
            $result = $this->process(
                $user,
                $payload['action'],
                $payload['selection'],
                $payload['params'] ?? [],
                fn (int $processed, int $failed) => $this->operations->progress($operation, $processed, $failed),
            );
            $operation->forceFill(['processed' => $result['processed'], 'failed' => $result['failed']]);
            $this->operations->complete($operation, $result);
        } catch (\Throwable $e) {
            $this->operations->fail($operation, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $params
     * @return array{processed: int, skipped: list<array{id: string, reason: string}>, failed: int}
     */
    public function process(?User $user, string $action, array $selection, array $params, ?callable $progress = null): array
    {
        $result = ['processed' => 0, 'skipped' => [], 'failed' => 0];
        $list = in_array($action, ['add_to_list', 'remove_from_list'], true)
            ? RecipientList::query()->where('uuid', $params['list_id'])->first()
            : null;

        if (in_array($action, ['add_to_list', 'remove_from_list'], true) && $list === null) {
            throw ValidationException::withMessages(['params.list_id' => ['Liste introuvable.']]);
        }

        $this->queries->selectionQuery($selection, $user)->chunkById(500, function (Collection $chunk) use (&$result, $action, $params, $list, $user, $progress): void {
            $outcome = match ($action) {
                'add_to_list' => $this->addToList($chunk, $list),
                'remove_from_list' => $this->removeFromList($chunk, $list),
                'blocklist' => $this->blocklist($chunk, $params, $user),
                'unblocklist' => $this->unblocklist($chunk),
                'update_attribute' => $this->updateAttribute($chunk, $params),
                'delete' => $this->delete($chunk),
            };

            $result['processed'] += $outcome['processed'];
            $result['failed'] += $outcome['failed'];
            foreach ($outcome['skipped'] as $skipped) {
                if (count($result['skipped']) < self::SKIPPED_CAP) {
                    $result['skipped'][] = $skipped;
                }
            }

            $progress?->__invoke($result['processed'], $result['failed']);
        }, 'recipients.id', 'id');

        if ($action === 'blocklist' && $result['processed'] > 0) {
            $this->audit->record('contacts.bulk_blocklisted', null, $user, null, ['count' => $result['processed']]);
        }

        if ($action === 'unblocklist' && $result['processed'] > 0) {
            $this->audit->record('contacts.bulk_unblocklisted', null, $user, null, ['count' => $result['processed']]);
        }

        if ($action === 'delete' && $result['processed'] > 0) {
            $this->audit->record('contacts.bulk_deleted', null, $user, null, ['count' => $result['processed']]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function assertParams(string $action, array $params): void
    {
        if (in_array($action, ['add_to_list', 'remove_from_list'], true) && empty($params['list_id'])) {
            throw ValidationException::withMessages(['params.list_id' => ['La liste est obligatoire.']]);
        }

        if ($action === 'update_attribute') {
            $name = (string) ($params['attribute'] ?? '');
            if (preg_match('/^[A-Za-z0-9_]{1,50}$/', $name) !== 1) {
                throw ValidationException::withMessages(['params.attribute' => ['Attribut invalide.']]);
            }
            if (strtoupper($name) === 'EMAIL') {
                throw ValidationException::withMessages(['params.attribute' => ['L’adresse e-mail ne peut pas être modifiée en masse.']]);
            }
            if (! array_key_exists('value', $params)) {
                throw ValidationException::withMessages(['params.value' => ['La valeur est obligatoire (null pour effacer).']]);
            }
        }
    }

    /**
     * @param  Collection<int, Recipient>  $chunk
     * @return array{processed: int, skipped: list<array{id: string, reason: string}>, failed: int}
     */
    private function addToList(Collection $chunk, RecipientList $list): array
    {
        $existing = DB::table('recipient_list_recipient')
            ->where('recipient_list_id', $list->id)->whereIn('recipient_id', $chunk->pluck('id'))
            ->pluck('recipient_id')->all();
        $new = $chunk->reject(fn (Recipient $r) => in_array($r->id, $existing, true));
        $skipped = $chunk->filter(fn (Recipient $r) => in_array($r->id, $existing, true))
            ->map(fn (Recipient $r) => ['id' => $r->uuid, 'reason' => 'Déjà dans la liste'])->values()->all();

        if ($new->isNotEmpty()) {
            if (app()->bound(self::LIST_MEMBERSHIP)) {
                app(self::LIST_MEMBERSHIP)->add($list, $new->pluck('id')->all(), 'bulk');
            } else {
                DB::table('recipient_list_recipient')->insertOrIgnore(
                    $new->map(fn (Recipient $r) => ['recipient_list_id' => $list->id, 'recipient_id' => $r->id, 'added_at' => now()])->all(),
                );
            }
        }

        return ['processed' => $new->count(), 'skipped' => $skipped, 'failed' => 0];
    }

    /**
     * @param  Collection<int, Recipient>  $chunk
     * @return array{processed: int, skipped: list<array{id: string, reason: string}>, failed: int}
     */
    private function removeFromList(Collection $chunk, RecipientList $list): array
    {
        $members = DB::table('recipient_list_recipient')
            ->where('recipient_list_id', $list->id)->whereIn('recipient_id', $chunk->pluck('id'))
            ->pluck('recipient_id')->all();
        $present = $chunk->filter(fn (Recipient $r) => in_array($r->id, $members, true));
        $skipped = $chunk->reject(fn (Recipient $r) => in_array($r->id, $members, true))
            ->map(fn (Recipient $r) => ['id' => $r->uuid, 'reason' => 'Absent de la liste'])->values()->all();

        if ($present->isNotEmpty()) {
            if (app()->bound(self::LIST_MEMBERSHIP)) {
                app(self::LIST_MEMBERSHIP)->remove($list, $present->pluck('id')->all(), 'bulk');
            } else {
                DB::table('recipient_list_recipient')
                    ->where('recipient_list_id', $list->id)->whereIn('recipient_id', $present->pluck('id'))->delete();
            }
        }

        return ['processed' => $present->count(), 'skipped' => $skipped, 'failed' => 0];
    }

    /**
     * @param  Collection<int, Recipient>  $chunk
     * @param  array<string, mixed>  $params
     * @return array{processed: int, skipped: list<array{id: string, reason: string}>, failed: int}
     */
    private function blocklist(Collection $chunk, array $params, ?User $user): array
    {
        $reason = SuppressionReason::tryFrom((string) ($params['reason'] ?? '')) ?? SuppressionReason::ManualBlock;
        $processed = 0;
        $skipped = [];

        foreach ($chunk as $recipient) {
            if ($recipient->status === RecipientStatus::Suppressed->value) {
                $skipped[] = ['id' => $recipient->uuid, 'reason' => 'Déjà en liste noire'];

                continue;
            }

            $this->suppression->suppress($recipient->email, $reason, null, null, $user?->id);
            $processed++;
        }

        if ($processed > 0 && ContactColumns::hasRecipientColumn('blocklisted_at')) {
            Recipient::query()->whereIn('id', $chunk->pluck('id'))->whereNull('blocklisted_at')
                ->where('status', RecipientStatus::Suppressed->value)->update(['blocklisted_at' => now()]);
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'failed' => 0];
    }

    /**
     * Only manual blocks are lifted here: entries for hard bounces, complaints,
     * unsubscribes… stay (those contacts are reported as skipped). Entries are
     * deleted one by one through Eloquent so the `blocklisted_at` mirror
     * listener (SyncRecipientBlocklist) and model events run.
     *
     * @param  Collection<int, Recipient>  $chunk
     * @return array{processed: int, skipped: list<array{id: string, reason: string}>, failed: int}
     */
    private function unblocklist(Collection $chunk): array
    {
        $isBlocked = fn (Recipient $r): bool => $r->status === RecipientStatus::Suppressed->value
            || $r->getAttribute('blocklisted_at') !== null;

        $skipped = $chunk->reject($isBlocked)
            ->map(fn (Recipient $r) => ['id' => $r->uuid, 'reason' => 'Pas en liste noire'])->values()->all();

        $blocked = $chunk->filter($isBlocked);
        if ($blocked->isEmpty()) {
            return ['processed' => 0, 'skipped' => $skipped, 'failed' => 0];
        }

        $entries = SuppressionEntry::query()->whereIn('email', $blocked->pluck('email')->unique()->values())->get()->groupBy('email');
        $unblocked = collect();

        foreach ($blocked as $recipient) {
            $own = $entries->get($recipient->email, collect());
            $other = $own->first(fn (SuppressionEntry $e) => $e->reason !== SuppressionReason::ManualBlock->value);

            if ($other !== null) {
                $skipped[] = ['id' => $recipient->uuid, 'reason' => 'Bloqué pour une autre raison ('.$other->reason.')'];

                continue;
            }

            $own->each->delete();
            $unblocked->push($recipient);
        }

        if ($unblocked->isNotEmpty()) {
            $update = ['status' => RecipientStatus::Active->value];
            if (ContactColumns::hasRecipientColumn('blocklisted_at')) {
                $update['blocklisted_at'] = null;
            }
            Recipient::query()->whereIn('id', $unblocked->pluck('id'))->update($update);
        }

        return ['processed' => $unblocked->count(), 'skipped' => $skipped, 'failed' => 0];
    }

    /**
     * @param  Collection<int, Recipient>  $chunk
     * @param  array<string, mixed>  $params
     * @return array{processed: int, skipped: list<array{id: string, reason: string}>, failed: int}
     */
    private function updateAttribute(Collection $chunk, array $params): array
    {
        $name = strtoupper((string) $params['attribute']);
        $value = $params['value'];
        $processed = 0;
        $skipped = [];

        foreach ($chunk as $recipient) {
            try {
                if (app()->bound(self::ATTRIBUTE_SERVICE)) {
                    app(self::ATTRIBUTE_SERVICE)->apply($recipient, [$name => $value], 'bulk');
                } elseif (isset(ContactColumns::SYSTEM_ATTRIBUTES[$name])) {
                    $recipient->forceFill([ContactColumns::SYSTEM_ATTRIBUTES[$name] => $value === null ? null : (string) $value])->save();
                } else {
                    $fields = $recipient->custom_fields ?? [];
                    if ($value === null) {
                        unset($fields[$name]);
                    } else {
                        $fields[$name] = $value;
                    }
                    $recipient->custom_fields = $fields;
                    $recipient->save();
                }
                $processed++;
            } catch (ValidationException $e) {
                $skipped[] = ['id' => $recipient->uuid, 'reason' => collect($e->errors())->flatten()->first() ?? 'Valeur invalide'];
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'failed' => 0];
    }

    /**
     * @param  Collection<int, Recipient>  $chunk
     * @return array{processed: int, skipped: list<array{id: string, reason: string}>, failed: int}
     */
    private function delete(Collection $chunk): array
    {
        foreach ($chunk as $recipient) {
            $recipient->delete();
            event(new RecipientDeleted($recipient->id, $recipient->email));
        }

        return ['processed' => $chunk->count(), 'skipped' => [], 'failed' => 0];
    }
}
