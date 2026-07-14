<?php

namespace App\Modules\Importing\Http\Controllers;

use App\Domain\Enums\ImportSourceType;
use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Importing\Http\Requests\StoreImportJobRequest;
use App\Modules\Importing\Http\Resources\ImportJobResource;
use App\Modules\Importing\Http\Resources\ImportRowResource;
use App\Modules\Importing\Jobs\CommitImportJob;
use App\Modules\Importing\Jobs\ParseImportFileJob;
use App\Modules\Importing\Models\ImportJob;
use App\Modules\Importing\Services\CsvImportService;
use App\Modules\Importing\Services\ExcelImportService;
use App\Modules\Importing\Services\ImportJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.4 — /api/v1/import-jobs.
 */
class ImportJobController extends Controller
{
    public function __construct(private readonly ImportJobService $jobs)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        return ImportJobResource::collection(
            ImportJob::query()->where('user_id', request()->user()->id)->latest()->paginate(25),
        );
    }

    public function store(StoreImportJobRequest $request, CsvImportService $csv, ExcelImportService $excel): JsonResponse
    {
        $sourceType = ImportSourceType::from($request->string('source_type')->toString());
        $job = $this->jobs->createFromUpload($request->file('file'), $sourceType, $request->user());

        $path = $this->jobs->absolutePath($job);
        $headers = $sourceType === ImportSourceType::Excel
            ? $this->firstRowKeys($excel->parse($path))
            : $this->firstRowKeys($csv->parse($path));

        return (new ImportJobResource($job))->additional(['detected_headers' => $headers])
            ->response()
            ->setStatusCode(201);
    }

    public function updateMapping(Request $request, string $uuid): ImportJobResource
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $validated = $request->validate([
            'column_mapping' => ['required', 'array'],
            'target_recipient_list_id' => ['nullable', 'integer', 'exists:recipient_lists,id'],
        ]);

        $job = ImportJob::query()->where('uuid', $uuid)->firstOrFail();
        $job->update([
            'column_mapping' => $validated['column_mapping'],
            'target_recipient_list_id' => $validated['target_recipient_list_id'] ?? null,
        ]);

        ParseImportFileJob::dispatch($job->id);

        return new ImportJobResource($job->fresh());
    }

    public function rows(string $uuid): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $job = ImportJob::query()->where('uuid', $uuid)->firstOrFail();

        return ImportRowResource::collection($job->rows()->paginate(50));
    }

    public function commit(Request $request, string $uuid): ImportJobResource
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $job = ImportJob::query()->where('uuid', $uuid)->firstOrFail();
        $excludedRowIds = $request->input('excluded_row_ids', []);

        CommitImportJob::dispatch($job->id, $excludedRowIds);

        return new ImportJobResource($job->fresh());
    }

    /**
     * @return list<string>
     */
    private function firstRowKeys(iterable $rows): array
    {
        foreach ($rows as $row) {
            return array_keys($row);
        }

        return [];
    }
}
