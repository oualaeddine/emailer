<?php

namespace App\Modules\Importing\Services;

use App\Domain\Enums\ImportJobStatus;
use App\Domain\Enums\ImportRowValidationStatus;
use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Importing\Events\ImportCompleted;
use App\Modules\Importing\Models\ImportJob;
use App\Modules\Importing\Models\ImportRow;
use App\Modules\Importing\Support\ImportSource;
use App\Modules\PageJaunesIntegration\Models\PageJaunesCompanyEmailCache;
use App\Modules\Recipients\DataTransferObjects\CreateRecipientData;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Services\RecipientService;
use App\Modules\Suppression\Services\SuppressionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * docs/14-import-export.md §14.2 — Commit stage: creates/links `recipients`
 * for every row marked `valid` (or user-approved), attaches to the target
 * list if specified, updates job counters.
 *
 * docs/45-brevo-api-contracts.md §45.5.7 — wizard jobs (mapping saved) also
 * handle `update_existing`, list membership (`list_ids` / `new_list`),
 * `mark_blocklisted`, `mark_opt_in` and write the `summary` JSON.
 */
class ImportCommitService
{
    private const LIST_CONTRACT = 'App\\Modules\\Recipients\\Lists\\Contracts\\ListMembershipService';

    private const ATTRIBUTE_CONTRACT = 'App\\Modules\\Recipients\\Attributes\\Contracts\\ContactAttributeService';

    /** Resolved once per commit (no per-row Schema / container probing). */
    private bool $hasExtId = false;

    private bool $hasOptIn = false;

    private bool $hasOptInSource = false;

    private bool $hasPageJaunesLink = false;

    private ?object $attributes = null;

    private ?object $listMembership = null;

    /** @var array<string, mixed> attribute definitions keyed by upper-cased name */
    private array $definitions = [];

    /** @var array<int, true> ids of rows whose e-mail already appeared on an earlier row */
    private array $repeatRowIds = [];

    public function __construct(
        private readonly RecipientService $recipients,
        private readonly SuppressionManager $suppression,
    ) {
    }

    private function prepare(ImportJob $job): void
    {
        $this->hasExtId = Schema::hasColumn('recipients', 'ext_id');
        $this->hasOptIn = Schema::hasColumn('recipients', 'opt_in_at');
        $this->hasOptInSource = Schema::hasColumn('recipients', 'opt_in_source');
        $this->hasPageJaunesLink = $job->source_type === ImportSource::PAGEJAUNES
            && Schema::hasColumn('recipients', 'pagejaunes_company_cache_id');
        $this->attributes = interface_exists(self::ATTRIBUTE_CONTRACT) ? app(self::ATTRIBUTE_CONTRACT) : null;
        $this->listMembership = interface_exists(self::LIST_CONTRACT) ? app(self::LIST_CONTRACT) : null;
        $this->definitions = $this->attributes !== null ? $this->attributes->definitions()->all() : [];
        $this->repeatRowIds = $this->repeatRowIds($job);
    }

    /**
     * @param  list<int>  $excludedRowIds
     */
    public function commit(ImportJob $job, array $excludedRowIds = []): void
    {
        $wizard = $job->isWizardJob();
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'blocklisted' => 0, 'added_to_lists' => 0];

        $job->update(['status' => $wizard ? 'importing' : ImportJobStatus::Processing->value, 'started_at' => $job->started_at ?? now()]);
        $this->prepare($job);

        $lists = $this->resolveLists($job);
        $source = match ($job->source_type) {
            ImportSource::CSV, ImportSource::TXT, ImportSource::PASTE => RecipientSource::CsvImport,
            ImportSource::EXCEL => RecipientSource::ExcelImport,
            ImportSource::PAGEJAUNES => RecipientSource::PageJaunes,
            default => RecipientSource::Manual,
        };

        $statuses = [ImportRowValidationStatus::Valid->value];
        if ($wizard) {
            $statuses[] = ImportRowValidationStatus::Duplicate->value;
        }

        $touchedIds = [];
        $processed = 0;

        $query = $job->rows()->whereIn('validation_status', $statuses);
        if ($excludedRowIds !== []) {
            $query->whereNotIn('id', $excludedRowIds);
        }

        foreach ($query->lazyById(200) as $row) {
            $processed++;

            try {
                $outcome = DB::transaction(fn () => $this->importRow($job, $row, $source, $wizard));
            } catch (Throwable $e) {
                $summary['failed']++;
                $job->errors()->create([
                    'row_number' => $row->row_number,
                    'error_code' => 'row_failed',
                    'message' => 'Ligne non importée : '.mb_substr($e->getMessage(), 0, 200),
                ]);

                continue;
            }

            if ($outcome['result'] === 'noop') {
                continue; // in-batch repeat, counted as skipped below
            }

            $summary[$outcome['result']]++;
            $summary['blocklisted'] += $outcome['blocklisted'] ? 1 : 0;

            if ($outcome['recipient'] !== null) {
                $touchedIds[] = $outcome['recipient'];
            }

            if ($processed % 100 === 0) {
                $job->update(['processed_rows' => $processed]);
            }
        }

        $summary['added_to_lists'] = $this->addToLists($job, $lists, $touchedIds);

        // Rows rejected at validation time (and in-batch duplicates) count as failed / skipped.
        $invalid = $job->rows()->where('validation_status', ImportRowValidationStatus::Invalid->value)->count();
        $inBatchDuplicates = $wizard ? count($this->repeatRowIds) : $job->rows()->where('validation_status', ImportRowValidationStatus::Duplicate->value)->count();
        $summary['failed'] += $invalid;
        $summary['skipped'] += $inBatchDuplicates;

        $job->update([
            'imported_count' => $summary['created'] + $summary['updated'],
            'duplicate_count' => $wizard ? $summary['skipped'] : $inBatchDuplicates,
            'error_count' => $summary['failed'],
            'processed_rows' => $job->total_rows,
            'summary' => $summary,
            'status' => $summary['failed'] > 0
                ? ImportJobStatus::CompletedWithErrors->value
                : ImportJobStatus::Completed->value,
            'completed_at' => now(),
        ]);

        ImportCompleted::dispatch($job->id, $summary);
    }

    /**
     * @return array{result: string, recipient: int|null, blocklisted: bool}
     */
    private function importRow(ImportJob $job, ImportRow $row, RecipientSource $source, bool $wizard): array
    {
        $data = $row->raw_data;
        $email = (string) $row->parsed_email;
        $isDuplicate = $row->validation_status === ImportRowValidationStatus::Duplicate->value;

        if ($isDuplicate) {
            // Duplicate rows only reach here for wizard jobs. In-batch repeats are counted as skipped later.
            if (isset($this->repeatRowIds[$row->id])) {
                return ['result' => 'noop', 'recipient' => null, 'blocklisted' => false];
            }

            $existing = Recipient::query()->where('email', $email)->first();

            if ($existing !== null) {
                if (! $job->update_existing) {
                    $row->update(['created_recipient_id' => $existing->id]);

                    return ['result' => 'skipped', 'recipient' => $existing->id, 'blocklisted' => $this->blocklist($job, $existing)];
                }

                $this->updateExisting($job, $existing, $data, $row);
                $row->update(['created_recipient_id' => $existing->id]);
                $this->markOptIn($job, $existing);

                return ['result' => 'updated', 'recipient' => $existing->id, 'blocklisted' => $this->blocklist($job, $existing)];
            }
            // The recipient vanished since validation: fall through and create it.
        }

        $custom = $this->nonNull($data['custom_fields'] ?? []);
        $recipient = $this->recipients->createOrMerge(new CreateRecipientData(
            email: $email,
            source: $source,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            companyName: $data['company_name'] ?? null,
            pageJaunesCompanyCacheId: $this->hasPageJaunesLink ? $this->pageJaunesCompanyId($email) : null,
            customFields: $wizard ? [] : $custom,
        ));

        if ($wizard) {
            $this->applyAttributes($recipient, $custom, 'import');
            $this->applyExtId($job, $recipient, $data, $row);
        }

        $row->update(['created_recipient_id' => $recipient->id]);

        if ($job->target_recipient_list_id !== null) {
            $job->targetRecipientList->recipients()->syncWithoutDetaching([
                $recipient->id => ['added_at' => now()],
            ]);
        }

        $this->markOptIn($job, $recipient);

        return ['result' => 'created', 'recipient' => $recipient->id, 'blocklisted' => $this->blocklist($job, $recipient)];
    }

    /**
     * `update_existing` merge: mapped values replace the stored ones; empty cells
     * only clear a value when `empty_values_overwrite` is on.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateExisting(ImportJob $job, Recipient $recipient, array $data, ImportRow $row): void
    {
        foreach (['first_name', 'last_name', 'company_name'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if ($data[$field] !== null) {
                $recipient->{$field} = $data[$field];
            } elseif ($job->empty_values_overwrite) {
                $recipient->{$field} = null;
            }
        }
        $recipient->save();

        $custom = $data['custom_fields'] ?? [];
        $set = $this->nonNull($custom);
        $this->applyAttributes($recipient, $set, 'import');

        if ($job->empty_values_overwrite) {
            $cleared = array_keys(array_filter($custom, fn ($v) => $v === null));
            if ($cleared !== []) {
                $fields = $recipient->fresh()->custom_fields ?? [];
                foreach ($cleared as $name) {
                    unset($fields[$name]);
                }
                $recipient->forceFill(['custom_fields' => $fields])->save();
            }
        }

        $this->applyExtId($job, $recipient, $data, $row);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applyAttributes(Recipient $recipient, array $values, string $source): void
    {
        if ($values === []) {
            return;
        }

        if ($this->attributes !== null) {
            $this->attributes->apply($recipient, $this->normaliseChoices($values), $source);

            return;
        }

        $recipient->forceFill([
            'custom_fields' => array_merge($recipient->custom_fields ?? [], $values),
        ])->save();
    }

    /**
     * Raw CSV strings -> shapes the attribute writer accepts: multiple_choice is split
     * on `,` / `;`, category and multiple_choice labels are mapped (case-insensitively)
     * to their option value.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normaliseChoices(array $values): array
    {
        foreach ($values as $name => $value) {
            $definition = $this->definitions[strtoupper((string) $name)] ?? null;
            if ($definition === null || ! in_array($definition->type, ['category', 'multiple_choice'], true) || ! is_string($value)) {
                continue;
            }

            $options = [];
            foreach ($definition->options ?? [] as $option) {
                $options[mb_strtolower(trim((string) $option['value']))] = (string) $option['value'];
                $options[mb_strtolower(trim((string) ($option['label'] ?? $option['value'])))] ??= (string) $option['value'];
            }
            $map = fn (string $item): string => $options[mb_strtolower(trim($item))] ?? trim($item);

            $values[$name] = $definition->type === 'multiple_choice'
                ? array_values(array_unique(array_map($map, array_filter(
                    array_map('trim', preg_split('/[,;]/', $value) ?: []),
                    fn (string $item) => $item !== '',
                ))))
                : $map($value);
        }

        return $values;
    }

    /**
     * `ext_id` is unique: a value already owned by another recipient is skipped with a row error.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyExtId(ImportJob $job, Recipient $recipient, array $data, ImportRow $row): void
    {
        $extId = $data['ext_id'] ?? null;
        if ($extId === null || ! $this->hasExtId) {
            return;
        }

        $owned = Recipient::withTrashed()->where('ext_id', $extId)->where('id', '!=', $recipient->id)->exists();
        if ($owned) {
            $job->errors()->create([
                'row_number' => $row->row_number,
                'error_code' => 'ext_id_conflict',
                'message' => 'EXT_ID « '.$extId.' » déjà utilisé par un autre contact : valeur ignorée.',
            ]);

            return;
        }

        $recipient->forceFill(['ext_id' => $extId])->save();
    }

    private function pageJaunesCompanyId(string $email): ?int
    {
        $id = PageJaunesCompanyEmailCache::query()->where('email', $email)->value('pagejaunes_company_cache_id');

        return $id !== null ? (int) $id : null;
    }

    private function markOptIn(ImportJob $job, Recipient $recipient): void
    {
        // `recipients.opt_in_at` is added by WP-52/WP-90 (Slot E); guard until it exists.
        if (! $job->mark_opt_in || ! $this->hasOptIn) {
            return;
        }

        $fill = [];
        if ($recipient->getAttribute('opt_in_at') === null) {
            $fill['opt_in_at'] = now();
            if ($this->hasOptInSource) {
                $fill['opt_in_source'] = 'import';
            }
        }
        if ($fill !== []) {
            $recipient->forceFill($fill)->save();
        }
    }

    private function blocklist(ImportJob $job, Recipient $recipient): bool
    {
        if (! $job->mark_blocklisted) {
            return false;
        }

        $this->suppression->suppress(
            $recipient->email,
            SuppressionReason::ManualBlock,
            notes: 'Import #'.$job->uuid,
            addedBy: $job->user_id,
        );

        return true;
    }

    /**
     * @return list<RecipientList>
     */
    private function resolveLists(ImportJob $job): array
    {
        $lists = [];

        $uuids = $job->list_ids ?? [];
        if ($uuids !== []) {
            $lists = RecipientList::query()->whereIn('uuid', $uuids)->get()->all();
        }

        if ($job->created_list_id !== null) {
            $created = RecipientList::query()->find($job->created_list_id);
            if ($created !== null) {
                $lists[] = $created;
            }
        } elseif (! empty($job->new_list['name'])) {
            $list = new RecipientList([
                'name' => $job->new_list['name'],
                'type' => 'static',
                'created_by' => $job->user_id,
            ]);
            if (! empty($job->new_list['folder_id']) && Schema::hasColumn('recipient_lists', 'folder_id')) {
                $folderId = DB::table('list_folders')->where('uuid', $job->new_list['folder_id'])->value('id');
                $list->setAttribute('folder_id', $folderId);
            }
            $list->save();
            $job->update(['created_list_id' => $list->id]);
            $lists[] = $list;
        }

        return $lists;
    }

    /**
     * @param  list<RecipientList>  $lists
     * @param  list<int>  $recipientIds
     */
    private function addToLists(ImportJob $job, array $lists, array $recipientIds): int
    {
        if ($lists === [] || $recipientIds === []) {
            return 0;
        }

        $added = 0;

        foreach ($lists as $list) {
            foreach (array_chunk(array_values(array_unique($recipientIds)), 500) as $chunk) {
                if ($this->listMembership !== null) {
                    $added += (int) $this->listMembership->add($list, $chunk, 'import', ['importJobId' => $job->id]);

                    continue;
                }

                $pivot = ['added_at' => now()];
                if (Schema::hasColumn('recipient_list_recipient', 'source')) {
                    $pivot['source'] = 'import';
                }
                $result = $list->recipients()->syncWithoutDetaching(array_fill_keys($chunk, $pivot));
                $added += count($result['attached']);
            }

            if (Schema::hasColumn('recipient_lists', 'last_import_at')) {
                $list->forceFill(['last_import_at' => now()])->save();
            }
        }

        return $added;
    }

    /**
     * Ids of rows whose e-mail already appeared on an earlier row (single ordered scan).
     *
     * @return array<int, true>
     */
    private function repeatRowIds(ImportJob $job): array
    {
        $seen = [];
        $repeats = [];

        foreach ($job->rows()->select(['id', 'parsed_email', 'validation_status'])->lazyById(1000) as $row) {
            $key = mb_strtolower((string) $row->parsed_email);
            if ($key === '') {
                continue;
            }
            if (isset($seen[$key]) && $row->validation_status === ImportRowValidationStatus::Duplicate->value) {
                $repeats[$row->id] = true;
            }
            $seen[$key] = true;
        }

        return $repeats;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function nonNull(array $values): array
    {
        return array_filter($values, fn ($v) => $v !== null && $v !== '');
    }
}
