<?php

namespace App\Modules\Campaigns\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/45-brevo-api-contracts.md §45.4.2/§45.4.5 — schedule refused because
 * blocking checklist items fail: `422` with `errors.checklist = [keys…]`.
 */
class CampaignChecklistBlockedException extends Exception
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(public readonly array $keys)
    {
        parent::__construct('La campagne ne peut pas être programmée : des éléments bloquants de la checklist ne sont pas validés.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['checklist' => $this->keys],
        ], 422);
    }
}
