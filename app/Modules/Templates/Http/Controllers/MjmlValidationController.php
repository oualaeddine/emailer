<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Templates\Http\Requests\ValidateMjmlRequest;
use App\Modules\Templates\Http\Resources\MjmlValidationResource;
use App\Modules\Templates\Services\CompiledMjml;
use App\Modules\Templates\Services\MjmlCompiler;

/**
 * docs/45-brevo-api-contracts.md §45.6.1 (WP-60) — POST /api/v1/templates/mjml/validate.
 *
 * Re-compiles the editor's MJML on the server (docs/46 §4) and returns the
 * sanitised HTML the hosts store as `html_body` / `html_content`. Invalid MJML
 * is a `200` with `valid: false` and the MJML errors (the contract's
 * `MjmlValidationResult`); a malformed request is a `422`.
 */
class MjmlValidationController extends Controller
{
    public function __invoke(ValidateMjmlRequest $request, MjmlCompiler $compiler): MjmlValidationResource
    {
        $compiled = $compiler->compile($request->string('mjml')->toString());

        $clientHtml = $request->input('html');
        if (! $compiled->compilerAvailable
            && config('editor.trust_client_html') === true
            && is_string($clientHtml)
            && trim($clientHtml) !== '') {
            $compiled = new CompiledMjml($compiler->sanitize($clientHtml), [], false);
        }

        return new MjmlValidationResource($compiled);
    }
}
