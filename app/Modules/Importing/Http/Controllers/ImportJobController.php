<?php

namespace App\Modules\Importing\Http\Controllers;

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
use App\Modules\Importing\Services\ImportMappingService;
use App\Modules\Importing\Services\ImportPreviewService;
use App\Modules\Importing\Services\PageJaunesContactResolver;
use App\Modules\Importing\Support\ImportSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * docs/29-api-specification.md §29.4 — /api/v1/import-jobs.
 * docs/45-brevo-api-contracts.md §45.5.7 — wizard + history endpoints.
 */
class ImportJobController extends Controller
{
    private const SORTS = ['created_at', '-created_at'];

    public function __construct(private readonly ImportJobService $jobs)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ImportJob::query()->with('user')->withCount('errors');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $direction = ($data['sort'] ?? '-created_at') === 'created_at' ? 'asc' : 'desc';
        $query->orderBy('created_at', $direction)->orderBy('id', $direction);

        return ImportJobResource::collection($query->paginate((int) ($data['per_page'] ?? 25)));
    }

    public function store(
        StoreImportJobRequest $request,
        CsvImportService $csv,
        ExcelImportService $excel,
        ImportPreviewService $previews,
    ): JsonResponse {
        $stored = ImportSource::normalise($request->string('source_type')->toString());

        $job = match ($stored) {
            ImportSource::PASTE => $this->jobs->createFromText(
                (string) $request->input('content'), ImportSource::PASTE, 'Copier-coller', $request->user(),
            ),
            ImportSource::PAGEJAUNES => $this->createFromPageJaunes($request),
            default => $this->jobs->createFromFile($request->file('file'), $stored, $request->user()),
        };

        $preview = $previews->build($job);
        $job->update(['total_rows' => $preview['total_rows']]);

        return (new ImportJobResource($job->load('user')))
            ->withPreview($preview)
            ->additional(['detected_headers' => $preview['headers']])
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): ImportJobResource
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        return new ImportJobResource($this->find($uuid));
    }

    public function preview(string $uuid, ImportPreviewService $previews): JsonResponse
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        return response()->json(['data' => $previews->build($this->find($uuid))]);
    }

    public function updateMapping(Request $request, string $uuid, ImportMappingService $mappings): ImportJobResource
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $job = $this->find($uuid);
        $this->assertEditable($job);

        if (! $request->has('mapping')) {
            return $this->updateLegacyMapping($request, $job);
        }

        $validated = $request->validate([
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*.column' => ['required', 'integer', 'min:0'],
            'mapping.*.header' => ['nullable', 'string', 'max:255'],
            'mapping.*.target' => ['required', 'array'],
            'mapping.*.target.type' => ['required', Rule::in(['attribute', 'ignore'])],
            'mapping.*.target.attribute' => ['required_if:mapping.*.target.type,attribute', 'nullable', 'string', 'regex:/^[A-Z][A-Z0-9_]{0,49}$/'],
            'create_attributes' => ['nullable', 'array', 'max:50'],
            'create_attributes.*.name' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9_]{0,49}$/'],
            'create_attributes.*.type' => ['required', Rule::in(['text', 'number', 'date', 'boolean'])],
        ]);

        $mappings->save($job, $validated, $request->user());
        $this->dispatchParse($job);

        return new ImportJobResource($job->fresh()->load('user'));
    }

    public function updateSettings(Request $request, string $uuid): ImportJobResource
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $job = $this->find($uuid);
        $this->assertEditable($job);

        $data = $request->validate([
            'list_ids' => ['present', 'array', 'max:50'],
            'list_ids.*' => ['uuid', Rule::exists('recipient_lists', 'uuid')->whereNull('deleted_at')],
            'new_list' => ['nullable', 'array'],
            'new_list.name' => ['required_with:new_list', 'string', 'max:150'],
            'new_list.folder_id' => ['nullable', 'uuid'],
            'update_existing' => ['required', 'boolean'],
            'empty_values_overwrite' => ['nullable', 'boolean'],
            'mark_blocklisted' => ['nullable', 'boolean'],
            'mark_opt_in' => ['nullable', 'boolean'],
        ]);

        if ($data['list_ids'] === [] && empty($data['new_list'])) {
            throw ValidationException::withMessages([
                'list_ids' => 'Sélectionnez au moins une liste ou créez-en une.',
            ]);
        }

        if (! empty($data['new_list'])) {
            Gate::authorize(PermissionName::RecipientsManage->value);
        }

        if (! empty($data['mark_blocklisted'])) {
            Gate::authorize(PermissionName::SuppressionManage->value);
        }

        $job->update([
            'list_ids' => array_values(array_unique($data['list_ids'])),
            'new_list' => empty($data['new_list'])
                ? null
                : ['name' => $data['new_list']['name'], 'folder_id' => $data['new_list']['folder_id'] ?? null],
            'update_existing' => $data['update_existing'],
            'empty_values_overwrite' => $data['empty_values_overwrite'] ?? false,
            'mark_blocklisted' => $data['mark_blocklisted'] ?? false,
            'mark_opt_in' => $data['mark_opt_in'] ?? false,
        ]);

        return new ImportJobResource($job->fresh()->load('user'));
    }

    public function rows(string $uuid): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $job = ImportJob::query()->where('uuid', $uuid)->firstOrFail();

        return ImportRowResource::collection($job->rows()->paginate(50));
    }

    public function commit(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $job = $this->find($uuid);
        $excludedRowIds = $request->input('excluded_row_ids', []);

        if (! $job->isWizardJob()) {
            CommitImportJob::dispatch($job->id, $excludedRowIds);

            return (new ImportJobResource($job->fresh()))->response();
        }

        if ($job->status !== 'ready') {
            return response()->json([
                'message' => 'Le fichier n’est pas prêt à être importé (statut : '.$job->status.').',
            ], 409);
        }

        if (($job->list_ids ?? []) === [] && empty($job->new_list) && $job->target_recipient_list_id === null) {
            throw ValidationException::withMessages([
                'list_ids' => 'Sélectionnez au moins une liste ou créez-en une.',
            ]);
        }

        if ($job->mark_blocklisted) {
            Gate::authorize(PermissionName::SuppressionManage->value);
        }

        // Atomic ready -> importing flip: a concurrent commit gets 0 rows and a 409.
        $flipped = ImportJob::query()->where('id', $job->id)->where('status', 'ready')->update(['status' => 'importing']);
        abort_if($flipped === 0, 409, 'Import déjà lancé.');

        CommitImportJob::dispatch($job->id, $excludedRowIds);

        return (new ImportJobResource($job->fresh()->load('user')))->response()->setStatusCode(202);
    }

    /**
     * `text/csv` — row_number; email; column; error.
     */
    public function errorsCsv(string $uuid): StreamedResponse
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $job = ImportJob::query()->where('uuid', $uuid)->firstOrFail();

        return response()->streamDownload(function () use ($job): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['row_number', 'email', 'column', 'error'], ';');

            $emails = $job->rows()->pluck('parsed_email', 'row_number');

            foreach ($job->errors()->orderBy('row_number')->orderBy('id')->cursor() as $error) {
                fputcsv($out, [
                    $error->row_number,
                    $error->row_number !== null ? ($emails[$error->row_number] ?? '') : '',
                    str_contains($error->error_code, 'email') ? 'EMAIL' : '',
                    $error->message,
                ], ';');
            }

            fclose($out);
        }, "import-{$job->uuid}-erreurs.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Mapping/settings are frozen once the import started: re-mapping would delete staged rows mid-commit. */
    private function assertEditable(ImportJob $job): void
    {
        abort_if(in_array($job->status, ['importing', 'completed', 'completed_with_errors'], true), 409, 'Import déjà lancé.');
    }

    private function find(string $uuid): ImportJob
    {
        return ImportJob::query()->with('user')->where('uuid', $uuid)->firstOrFail();
    }

    private function updateLegacyMapping(Request $request, ImportJob $job): ImportJobResource
    {
        $validated = $request->validate([
            'column_mapping' => ['required', 'array'],
            'target_recipient_list_id' => ['nullable', 'integer', 'exists:recipient_lists,id'],
        ]);

        $job->update([
            'column_mapping' => $validated['column_mapping'],
            'target_recipient_list_id' => $validated['target_recipient_list_id'] ?? null,
        ]);

        ParseImportFileJob::dispatch($job->id);

        return new ImportJobResource($job->fresh());
    }

    private function dispatchParse(ImportJob $job): void
    {
        try {
            ParseImportFileJob::dispatch($job->id);
        } catch (Throwable $e) {
            // The job already recorded the failure (status `failed` + import_errors).
            report($e);
        }
    }

    private function createFromPageJaunes(StoreImportJobRequest $request): ImportJob
    {
        $contacts = app(PageJaunesContactResolver::class)->resolve(
            (array) $request->input('external_ids', []),
            (array) $request->input('contacts', []),
        );

        if ($contacts === []) {
            throw ValidationException::withMessages([
                'contacts' => 'Aucune adresse e-mail à importer pour les entreprises sélectionnées.',
            ]);
        }

        // The company id is NOT written as EXT_ID (unique per recipient): the commit links
        // recipients to `pagejaunes_company_cache` by e-mail instead.
        $out = fopen('php://temp', 'w+b');
        fputcsv($out, ['EMAIL', 'SOCIETE'], ';');
        foreach ($contacts as $contact) {
            fputcsv($out, [$contact['email'], $contact['company_name']], ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->jobs->createFromText($csv, ImportSource::PAGEJAUNES, 'PagesJaunes', $request->user());
    }
}
