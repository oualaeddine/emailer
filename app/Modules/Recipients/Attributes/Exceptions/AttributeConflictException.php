<?php

namespace App\Modules\Recipients\Attributes\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** 409 for system attributes, attributes in use, options in use (docs/45 §45.5.1). */
class AttributeConflictException extends Exception
{
    /** @param list<array{type: string, id: string, name: string}> $usedIn */
    public function __construct(string $message, public readonly array $usedIn = [])
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        $body = ['message' => $this->getMessage()];
        if ($this->usedIn !== []) {
            $body['errors'] = ['used_in' => $this->usedIn];
        }

        return response()->json($body, 409);
    }
}
