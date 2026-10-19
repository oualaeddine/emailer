<?php

namespace App\Modules\Senders\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Senders\Contracts\DnsResolverContract;
use App\Modules\Senders\Events\DomainVerificationCompleted;
use App\Modules\Senders\Jobs\VerifyDomainJob;
use App\Modules\Senders\Models\Sender;
use App\Modules\Senders\Models\SendingDomain;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** docs/43 §43.4.14 — domain authentication (DKIM / SPF / DMARC records + DNS checks). */
class DomainAuthService
{
    public function __construct(
        private readonly DnsResolverContract $dns,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
        private readonly SenderService $senders,
    ) {}

    public function findByUuid(string $uuid): SendingDomain
    {
        return SendingDomain::query()->withCount('senders')->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SendingDomain>
     */
    public function all()
    {
        return SendingDomain::query()->withCount('senders')->orderBy('domain')->get();
    }

    public function create(string $domain, ?User $actor): SendingDomain
    {
        $domain = strtolower(trim($domain));
        $selector = 'pjm1';
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $publicPem = openssl_pkey_get_details($key)['key'];
        $publicB64 = preg_replace('/-----[A-Z ]+-----|\s+/', '', $publicPem);

        $model = DB::transaction(function () use ($domain, $selector, $privatePem, $publicPem, $publicB64, $actor): SendingDomain {
            $model = SendingDomain::query()->create([
                'domain' => $domain,
                'status' => SendingDomain::STATUS_PENDING,
                'dkim_selector' => $selector,
                'dkim_private_key' => $privatePem,
                'dkim_public_key' => $publicPem,
                'records' => $this->buildRecords($domain, $selector, $publicB64),
                'created_by' => $actor?->id,
            ]);
            Sender::query()->whereNull('domain_id')->where('email', 'like', '%@'.$domain)->update(['domain_id' => $model->id]);

            return $model;
        });

        $this->audit->record('domain.created', $model, $actor, null, ['domain' => $domain]);
        $this->audit->record('domain.dkim_generated', $model, $actor, null, ['selector' => $selector]);

        return $model->loadCount('senders');
    }

    public function requestVerification(SendingDomain $domain): SendingDomain
    {
        $domain->update(['status' => SendingDomain::STATUS_CHECKING]);
        VerifyDomainJob::dispatch($domain->id);

        return $domain->loadCount('senders');
    }

    public function delete(SendingDomain $domain, ?User $actor): void
    {
        $hasVerified = $domain->senders()->whereNotNull('verified_at')->exists();
        if ($hasVerified) {
            throw new ConflictHttpException('Des expéditeurs vérifiés utilisent encore ce domaine.');
        }

        $this->audit->record('domain.deleted', $domain, $actor, ['domain' => $domain->domain]);
        $domain->delete();
    }

    /** Runs the DNS lookups, stores the per-record status and the overall status. */
    public function runChecks(SendingDomain $domain): SendingDomain
    {
        $now = now();
        $records = array_map(function (array $record) use ($domain, $now): array {
            [$status, $found] = match ($record['kind']) {
                'spf' => $this->checkSpf($record),
                'dkim' => $this->checkDkim($domain, $record),
                default => $this->checkDmarc($record),
            };

            return [...$record, 'status' => $status, 'found_value' => $found, 'checked_at' => $now->toIso8601ZuluString()];
        }, $domain->records ?? []);

        $domain->records = $records;
        $domain->last_checked_at = $now;
        $domain->status = $this->overallStatus($records);
        $domain->save();

        $this->audit->record('domain.verified', $domain, null, null, ['status' => $domain->status]);
        DomainVerificationCompleted::dispatch($domain->id, $domain->status);
        if ($domain->status === SendingDomain::STATUS_VERIFIED) {
            $this->senders->autoVerifyForDomain($domain);
        }

        return $domain;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function overallStatus(array $records): string
    {
        $status = collect($records)->mapWithKeys(fn ($r) => [$r['kind'] => $r['status']]);
        if (($status['spf'] ?? null) === 'valid' && ($status['dkim'] ?? null) === 'valid') {
            return SendingDomain::STATUS_VERIFIED;
        }

        return $status->contains('valid') ? SendingDomain::STATUS_PARTIAL : SendingDomain::STATUS_FAILED;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: string, 1: string|null}
     */
    private function checkSpf(array $record): array
    {
        $include = $this->spfInclude();
        $found = collect($this->dns->txt($record['host']))->first(fn ($t) => stripos(trim($t, '" '), 'v=spf1') === 0);
        if ($found === null) {
            return ['missing', null];
        }

        return [stripos($found, 'include:'.$include) !== false ? 'valid' : 'invalid', $found];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: string, 1: string|null}
     */
    private function checkDkim(SendingDomain $domain, array $record): array
    {
        $expected = $this->normalise((string) preg_replace('/^.*p=/', '', $record['value']));
        $candidates = array_values(array_filter(
            $this->dns->txt($record['host']),
            fn ($t) => stripos($t, 'v=DKIM1') !== false || stripos($t, 'p=') !== false,
        ));
        if ($candidates === []) {
            $cname = $this->dns->cname($record['host']);
            if ($cname === []) {
                return ['missing', null];
            }
            // Delegated DKIM: the CNAME target must itself publish the expected key.
            foreach ($this->dns->txt(rtrim($cname[0], '.')) as $candidate) {
                if ($expected !== '' && str_contains($this->normalise($candidate), $expected)) {
                    return ['valid', $cname[0]];
                }
            }

            return ['invalid', $cname[0]];
        }
        foreach ($candidates as $candidate) {
            if ($expected !== '' && str_contains($this->normalise($candidate), $expected)) {
                return ['valid', $candidate];
            }
        }

        return ['invalid', $candidates[0]];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: string, 1: string|null}
     */
    private function checkDmarc(array $record): array
    {
        $found = collect($this->dns->txt($record['host']))->first(fn ($t) => stripos(trim($t, '" '), 'v=DMARC1') === 0);
        if ($found === null) {
            return ['missing', null];
        }

        return [preg_match('/;\s*p=(none|quarantine|reject)/i', $found) === 1 ? 'valid' : 'invalid', $found];
    }

    private function normalise(string $value): string
    {
        return (string) preg_replace('/[\s"\\\\]+/', '', $value);
    }

    private function spfInclude(): string
    {
        return (string) ($this->settings->get('senders.spf_include')
            ?: '_spf.'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecords(string $domain, string $selector, string $publicB64): array
    {
        $rua = (string) ($this->settings->get('senders.dmarc_rua') ?: 'dmarc@'.$domain);
        $rua = str_starts_with($rua, 'mailto:') ? $rua : 'mailto:'.$rua;
        $base = ['type' => 'TXT', 'status' => 'pending', 'found_value' => null, 'checked_at' => null];

        return [
            [...$base, 'kind' => 'spf', 'host' => $domain, 'value' => 'v=spf1 include:'.$this->spfInclude().' ~all'],
            [...$base, 'kind' => 'dkim', 'host' => $selector.'._domainkey.'.$domain, 'value' => 'v=DKIM1; k=rsa; p='.$publicB64],
            [...$base, 'kind' => 'dmarc', 'host' => '_dmarc.'.$domain, 'value' => 'v=DMARC1; p=none; rua='.$rua],
        ];
    }
}
