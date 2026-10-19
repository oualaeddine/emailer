<?php

namespace App\Modules\Importing\Services;

use App\Modules\PageJaunesIntegration\Models\PageJaunesCompanyCache;
use App\Modules\PageJaunesIntegration\Repositories\PageJaunesCompanyRepository;
use App\Modules\PageJaunesIntegration\Services\PageJaunesSyncService;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Resolves the e-mails of the PagesJaunes companies picked in the wizard from the
 * server-side `pagejaunes_company_cache` (companies missing from the cache are
 * fetched once through the integration's own repository/sync service) — the client
 * only ever names companies, never trusted addresses.
 */
class PageJaunesContactResolver
{
    /**
     * @param  list<int|string>  $externalIds
     * @param  list<array<string, mixed>>  $requested  optional subset sent by the client
     * @return list<array{email: string, company_name: string}>
     */
    public function resolve(array $externalIds, array $requested = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $externalIds)));
        $companies = $this->load($ids);

        $missing = array_diff($ids, $companies->pluck('pagejaunes_company_id')->map(fn ($v) => (int) $v)->all());
        if ($missing !== []) {
            $this->hydrate($missing);
            $companies = $this->load($ids);
        }

        /** @var array<string, string> $allowed lower-cased e-mail => company name */
        $allowed = [];
        foreach ($companies as $company) {
            $name = (string) ($company->company_name ?? $company->abv_company_name ?? '');
            foreach ($company->emails as $email) {
                $allowed[mb_strtolower(trim($email->email))] ??= $name;
            }
        }

        if ($requested === []) {
            $emails = array_keys($allowed);
        } else {
            $emails = [];
            foreach ($requested as $i => $contact) {
                $email = mb_strtolower(trim((string) ($contact['email'] ?? '')));
                if (! isset($allowed[$email])) {
                    throw ValidationException::withMessages([
                        "contacts.{$i}.email" => 'Cette adresse n’appartient à aucune des entreprises sélectionnées.',
                    ]);
                }
                $emails[$email] = $email;
            }
            $emails = array_values($emails);
        }

        return array_map(fn (string $email) => ['email' => $email, 'company_name' => $allowed[$email]], $emails);
    }

    /** @param  list<int>  $ids */
    private function load(array $ids)
    {
        return PageJaunesCompanyCache::query()
            ->whereIn('pagejaunes_company_id', $ids)
            ->with(['emails' => fn ($q) => $q->whereNull('source_deleted_at')])
            ->get();
    }

    /** @param  list<int>  $ids */
    private function hydrate(array $ids): void
    {
        try {
            $repository = app(PageJaunesCompanyRepository::class);
            $sync = app(PageJaunesSyncService::class);
            foreach ($ids as $id) {
                $dto = $repository->findByExternalId($id);
                if ($dto !== null) {
                    $sync->upsertCompany($dto);
                }
            }
        } catch (Throwable $e) {
            report($e); // source DB unreachable: those companies simply resolve to no address.
        }
    }
}
