<?php

namespace App\Modules\Importing\Services;

use App\Domain\Enums\ImportJobStatus;
use App\Domain\Enums\ImportRowValidationStatus;
use App\Domain\Enums\RecipientSource;
use App\Modules\Importing\Models\ImportJob;
use App\Modules\Recipients\DataTransferObjects\CreateRecipientData;
use App\Modules\Recipients\Services\RecipientService;
use Illuminate\Support\Facades\DB;

/**
 * docs/14-import-export.md §14.2 — Commit stage: creates/links `recipients`
 * for every row marked `valid` (or user-approved), attaches to the target
 * list if specified, updates job counters.
 */
class ImportCommitService
{
    public function __construct(private readonly RecipientService $recipients)
    {
    }

    /**
     * @param  list<int>  $excludedRowIds
     */
    public function commit(ImportJob $job, array $excludedRowIds = []): void
    {
        DB::transaction(function () use ($job, $excludedRowIds): void {
            $rows = $job->rows()
                ->whereIn('validation_status', [ImportRowValidationStatus::Valid->value])
                ->whereNotIn('id', $excludedRowIds)
                ->get();

            $importedCount = 0;
            $source = match ($job->source_type) {
                'csv' => RecipientSource::CsvImport,
                'excel' => RecipientSource::ExcelImport,
                'pagejaunes' => RecipientSource::PageJaunes,
                default => RecipientSource::Manual,
            };

            foreach ($rows as $row) {
                $data = $row->raw_data;

                $recipient = $this->recipients->createOrMerge(new CreateRecipientData(
                    email: $row->parsed_email,
                    source: $source,
                    firstName: $data['first_name'] ?? null,
                    lastName: $data['last_name'] ?? null,
                    companyName: $data['company_name'] ?? null,
                    customFields: $data['custom_fields'] ?? [],
                ));

                $row->update(['created_recipient_id' => $recipient->id]);

                if ($job->target_recipient_list_id !== null) {
                    $job->targetRecipientList->recipients()->syncWithoutDetaching([
                        $recipient->id => ['added_at' => now()],
                    ]);
                }

                $importedCount++;
            }

            $duplicateCount = $job->rows()->where('validation_status', ImportRowValidationStatus::Duplicate->value)->count();
            $errorCount = $job->rows()->where('validation_status', ImportRowValidationStatus::Invalid->value)->count();

            $job->update([
                'imported_count' => $importedCount,
                'duplicate_count' => $duplicateCount,
                'error_count' => $errorCount,
                'processed_rows' => $job->total_rows,
                'status' => $errorCount > 0
                    ? ImportJobStatus::CompletedWithErrors->value
                    : ImportJobStatus::Completed->value,
                'completed_at' => now(),
            ]);
        });
    }
}
