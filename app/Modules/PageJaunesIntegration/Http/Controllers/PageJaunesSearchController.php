<?php

namespace App\Modules\PageJaunesIntegration\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\PageJaunesIntegration\Http\Resources\PageJaunesCompanyResource;
use App\Modules\PageJaunesIntegration\Repositories\PageJaunesCompanyRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.4 — GET /api/v1/pagejaunes/search.
 * docs/06-pagejaunes-integration.md §6.9 — 5-minute Redis (cache-store)
 * cache on hot search queries, independent of the persisted company cache.
 */
class PageJaunesSearchController extends Controller
{
    public function __construct(private readonly PageJaunesCompanyRepository $repository)
    {
    }

    public function __invoke(Request $request): array
    {
        Gate::authorize(PermissionName::RecipientsImport->value);

        $query = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        if ($query === '') {
            return ['data' => [], 'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => 0]];
        }

        $cacheKey = 'pagejaunes.search.'.md5($query).'.'.$page;

        $results = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query, $perPage, $page) {
            return $this->repository->search($query, $perPage, ($page - 1) * $perPage);
        });

        return [
            'data' => PageJaunesCompanyResource::collection($results)->resolve(),
            'meta' => ['current_page' => $page, 'per_page' => $perPage],
        ];
    }
}
