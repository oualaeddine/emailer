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
     */
    public function validateAndStage(ImportJob $job, iterable $rawRows, array $columnMapping): void
    {
        $seenEmailsInBatch = [];
        $rowNumber = 0;

        foreach ($rawRows as $rawRow) {
            $rowNumber++;
            $mapped = $this->applyMapping($rawRow, $columnMapping);
            $email = isset($mapped['email']) ? trim((string) $mapped['email']) : null;

            $status = $this->determineStatus($email, $seenEmailsInBatch);

            if ($email !== null && $email !== '') {
                $seenEmailsInBatch[mb_strtolower($email)] = true;
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
