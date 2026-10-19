<?php

namespace App\Modules\Senders\Jobs;

use App\Modules\Senders\Models\SendingDomain;
use App\Modules\Senders\Services\DomainAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** docs/45 §45.9 — payload `domainId`. */
class VerifyDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $domainId) {}

    public function handle(DomainAuthService $domains): void
    {
        $domain = SendingDomain::query()->find($this->domainId);
        if ($domain !== null) {
            $domains->runChecks($domain);
        }
    }
}
