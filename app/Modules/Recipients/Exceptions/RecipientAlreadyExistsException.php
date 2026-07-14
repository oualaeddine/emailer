<?php

namespace App\Modules\Recipients\Exceptions;

use App\Modules\Recipients\Http\Resources\RecipientResource;
use App\Modules\Recipients\Models\Recipient;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/29-api-specification.md §29.4 — POST /api/v1/recipients:
 * "manual create; 409 if email exists (returns existing recipient link
 * suggestion)". Distinct from {@see \App\Modules\Recipients\Services\RecipientService::createOrMerge()},
 * which silently merges — that path is for bulk import/PageJaunes flows
 * (docs/13-recipient-management.md §13.4), not the direct manual-create API.
 */
class RecipientAlreadyExistsException extends Exception
{
    public function __construct(public readonly Recipient $existing)
    {
        parent::__construct('A recipient with this email already exists.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Un destinataire avec cette adresse e-mail existe déjà.',
            'existing_recipient' => (new RecipientResource($this->existing))->resolve($request),
        ], 409);
    }
}
