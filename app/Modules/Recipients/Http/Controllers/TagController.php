<?php

namespace App\Modules\Recipients\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Http\Resources\TagResource;
use App\Modules\Recipients\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * docs/13-recipient-management.md §13.5 — Tagging.
 */
class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsView->value);

        return TagResource::collection(Tag::query()->orderBy('name')->get());
    }

    public function store(Request $request): TagResource
    {
        Gate::authorize(PermissionName::RecipientsManage->value);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:tags,name'],
            'color' => ['sometimes', 'string', 'max:20'],
        ]);

        return new TagResource(Tag::query()->create($validated));
    }
}
