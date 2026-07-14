<?php

namespace App\Modules\Composer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Composer\Http\Requests\SaveSignatureRequest;
use App\Modules\Composer\Http\Resources\SignatureResource;
use App\Modules\Composer\Models\Signature;
use App\Modules\Composer\Services\SignatureService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * docs/29-api-specification.md §29.3 — /api/v1/signatures (own signatures).
 */
class SignatureController extends Controller
{
    public function __construct(private readonly SignatureService $signatures)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return SignatureResource::collection($this->signatures->forUser(request()->user()));
    }

    public function store(SaveSignatureRequest $request): SignatureResource
    {
        $signature = $this->signatures->create(
            $request->user(),
            $request->string('name')->toString(),
            $request->string('html_content')->toString(),
            $request->boolean('is_default'),
        );

        return new SignatureResource($signature);
    }

    public function update(SaveSignatureRequest $request, Signature $signature): SignatureResource
    {
        abort_unless($signature->user_id === $request->user()->id, 403);

        $updated = $this->signatures->update(
            $signature,
            $request->string('name')->toString(),
            $request->string('html_content')->toString(),
            $request->boolean('is_default'),
        );

        return new SignatureResource($updated);
    }
}
