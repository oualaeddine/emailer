<?php

namespace App\Modules\Importing\Services;

use App\Domain\Enums\ImportRowValidationStatus;
use App\Modules\Importing\Models\ImportJob;
use App\Modules\Importing\Models\ImportRow;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Support\Facades\Validator;

/**
 * docs/14-import-export.md §14.2 — Validate stage.
 * Per-row: syntax email check, required-field check, duplicate check
 * against existing `recipients.email` and within the batch itself.
 */
class ImportValidationService
{
    /**
     * @param  iterable<array<string, mixed>>  $rawRows
     * @param  array<string, string>  $columnMapping  target field => source column name
     * @param  list<array<string, mixed>>|null  $wizardMapping  §45.5.7 column mappings; when given, unmapped columns are ignored
     */
    public function validateAndStage(ImportJob $job, iterable $rawRows, array $columnMapping, ?array $wizardMapping = null): void
    {
        // Re-mapping restarts the staging (idempotent wizard).
        $job->rows()->delete();
        $job->errors()->delete();

        $seenEmailsInBatch = [];
        $rowNumber = 0;

        foreach ($rawRows as $rawRow) {
            $rowNumber++;
            $mapped = $wizardMapping !== null
                ? $this->applyWizardMapping($rawRow, $wizardMapping)
                : $this->applyMapping($rawRow, $columnMapping);
            $email = isset($mapped['email']) ? trim((string) $mapped['email']) : null;

            $status = $this->determineStatus($email, $seenEmailsInBatch);

            if ($email !== null && $email !== '') {
                $seenEmailsInBatch[mb_strtolower($email)] = true;
            }

            if ($status === ImportRowValidationStatus::Invalid) {
                $job->errors()->create([
                    'row_number' => $rowNumber,
                    'error_code' => $email === null || $email === '' ? 'missing_email' : 'invalid_email',
                    'message' => $email === null || $email === ''
                        ? 'Adresse e-mail manquante.'
                        : 'Adresse e-mail invalide.',
                ]);
            }

            ImportRow::query()->create([
                'import_job_id' => $job->id,
                'row_number' => $rowNumber,
                'raw_data' => $mapped,
                'parsed_email' => $email,
                'validation_status' => $status->value,
            ]);
        }

        $job->update(['total_rows' => $rowNumber]);
    }

    /**
     * docs/45-brevo-api-contracts.md §45.5.7 — each CSV column maps to an
     * attribute name or is ignored. System attributes land in their recipients
     * column, everything else in `custom_fields[NAME]`; empty cells are kept as
     * null so the commit can honour `empty_values_overwrite`.
     *
     * @param  array<string, mixed>  $rawRow
     * @param  list<array<string, mixed>>  $wizardMapping
     * @return array<string, mixed>
     */
    private function applyWizardMapping(array $rawRow, array $wizardMapping): array
    {
        $values = array_values($rawRow);
        $mapped = [];
        $customFields = [];

        foreach ($wizardMapping as $entry) {
            if (($entry['target']['type'] ?? 'ignore') !== 'attribute') {
                continue;
            }

            $header = (string) ($entry['header'] ?? '');
            $value = array_key_exists($header, $rawRow) ? $rawRow[$header] : ($values[$entry['column']] ?? null);
            $value = is_string($value) ? trim($value) : $value;
            $value = $value === '' ? null : $value;

            $attribute = $entry['target']['attribute'];
            $field = ImportAttributeCatalog::SYSTEM[$attribute] ?? null;

            if ($field === null || str_starts_with($field, 'custom:')) {
                $customFields[$field === null ? $attribute : substr($field, 7)] = $value;
            } else {
                $mapped[$field] = $value;
            }
        }

        if ($customFields !== []) {
            $mapped['custom_fields'] = $customFields;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $rawRow
     * @param  array<string, string>  $columnMapping
     * @return array<string, mixed>
     */
    private function applyMapping(array $rawRow, array $columnMapping): array
    {
        $mapped = [];
        $customFields = [];

        foreach ($rawRow as $sourceColumn => $value) {
            $targetField = array_search($sourceColumn, $columnMapping, true);

            if ($targetField !== false) {
                $mapped[$targetField] = $value;
            } elseif ($value !== null && $value !== '') {
                $customFields[$sourceColumn] = $value;
            }
        }

        if ($customFields !== []) {
            $mapped['custom_fields'] = $customFields;
        }

        return $mapped;
    }

    /**
     * @param  array<string, true>  $seenEmailsInBatch
     */
    private function determineStatus(?string $email, array $seenEmailsInBatch): ImportRowValidationStatus
    {
        if ($email === null || $email === '') {
            return ImportRowValidationStatus::Invalid;
        }

        $validator = Validator::make(['email' => $email], ['email' => ['email']]);
        if ($validator->fails()) {
            return ImportRowValidationStatus::Invalid;
        }

        if (isset($seenEmailsInBatch[mb_strtolower($email)])) {
            return ImportRowValidationStatus::Duplicate;
        }

        if (Recipient::query()->where('email', $email)->exists()) {
            return ImportRowValidationStatus::Duplicate;
        }

        return ImportRowValidationStatus::Valid;
    }
}
