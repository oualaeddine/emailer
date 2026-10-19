<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Templates\Http\Requests\ImportTemplateFromUrlRequest;
use App\Modules\Templates\Http\Requests\ImportTemplateZipRequest;
use App\Modules\Templates\Services\TemplateUrlImportService;
use App\Modules\Templates\Services\TemplateZipImportService;
use Illuminate\Http\JsonResponse;

/**
 * docs/45 §45.4.4 — HTML import helpers for the template picker "Coder" tab.
 * Nothing is persisted; the picker saves via `PUT /campaigns/{id}/design`.
 */
class TemplateImportController extends Controller
{
    public function fromUrl(ImportTemplateFromUrlRequest $request, TemplateUrlImportService $importer): JsonResponse
    {
        return response()->json(['data' => $importer->import($request->string('url')->toString())]);
    }

    public function zip(ImportTemplateZipRequest $request, TemplateZipImportService $importer): JsonResponse
    {
        return response()->json(['data' => $importer->import($request->file('file'))]);
    }
}
