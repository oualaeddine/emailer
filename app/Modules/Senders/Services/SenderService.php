<?php

namespace App\Modules\Senders\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Senders\Events\SenderVerified;
use App\Modules\Senders\Mail\SenderVerificationMail;
use App\Modules\Senders\Models\Sender;
use App\Modules\Senders\Models\SendingDomain;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** docs/43 §43.4.14 / docs/45 §45.4.1 — senders CRUD, verification, default flag. */
class SenderService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Sender>
     */
    public function list(array $filters = []): Collection
    {
        $query = Sender::query()->with('domain')->orderByDesc('is_default')->orderBy('name');
        if (! empty($filters['verified'])) {
            $query->whereNotNull('verified_at');
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(fn ($w) => $w->where('name', 'like', $q)->orWhere('email', 'like', $q));
        }

        return $query->get();
    }

    public function findByUuid(string $uuid): Sender
    {
        return Sender::query()->with('domain')->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor): Sender
    {
        $email = strtolower($data['email']);
        $domain = SendingDomain::query()->where('domain', Sender::domainOf($email))->first();
        $wantsDefault = (bool) ($data['is_default'] ?? false);
        $autoVerified = $domain?->status === SendingDomain::STATUS_VERIFIED;

        if ($wantsDefault && ! $autoVerified) {
            throw new ConflictHttpException('Un expéditeur doit être vérifié avant de devenir l\'expéditeur par défaut.');
        }

        $sender = DB::transaction(function () use ($data, $email, $domain, $autoVerified, $wantsDefault, $actor): Sender {
            $sender = Sender::query()->create([
                'name' => $data['name'],
                'email' => $email,
                'reply_to' => $data['reply_to'] ?? null,
                'domain_id' => $domain?->id,
                'created_by' => $actor?->id,
                'verification_method' => $autoVerified ? 'domain' : null,
                'verified_at' => $autoVerified ? now() : null,
            ]);
            if ($wantsDefault) {
                $this->makeDefault($sender);
            }

            return $sender;
        });

        $this->audit->record('sender.created', $sender, $actor, null, ['email' => $email]);

        if ($autoVerified) {
            $this->audit->record('sender.verified', $sender, $actor, null, ['method' => 'domain']);
            SenderVerified::dispatch($sender->id);
        } else {
            $this->sendVerification($sender);
        }

        return $sender->refresh()->load('domain');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Sender $sender, array $data): Sender
    {
        $sender->update(array_intersect_key($data, array_flip(['name', 'reply_to'])));

        return $sender->refresh()->load('domain');
    }

    public function delete(Sender $sender, ?User $actor): void
    {
        if ($sender->is_default) {
            throw new ConflictHttpException('L\'expéditeur par défaut ne peut pas être supprimé.');
        }
        if ($this->usedByActiveCampaign($sender)) {
            throw new ConflictHttpException('Cet expéditeur est utilisé par une campagne planifiée ou en cours d\'envoi.');
        }

        $this->audit->record('sender.deleted', $sender, $actor, ['email' => $sender->email]);
        $sender->delete();
    }

    public function setDefault(Sender $sender): Sender
    {
        if (! $sender->isVerified()) {
            throw new ConflictHttpException('Un expéditeur doit être vérifié avant de devenir l\'expéditeur par défaut.');
        }
        DB::transaction(fn () => $this->makeDefault($sender));

        return $sender->refresh()->load('domain');
    }

    public function resendVerification(Sender $sender): Sender
    {
        if ($sender->isVerified()) {
            throw new ConflictHttpException('Cet expéditeur est déjà vérifié.');
        }
        $this->sendVerification($sender);

        return $sender->refresh()->load('domain');
    }

    public function verifyByLink(string $uuid): Sender
    {
        $sender = $this->findByUuid($uuid);
        if (! $sender->isVerified()) {
            $this->markVerified($sender, 'email_link', null);
        }

        return $sender;
    }

    public function autoVerifyForDomain(SendingDomain $domain): void
    {
        $domain->senders()->whereNull('verified_at')->get()
            ->each(fn (Sender $s) => $this->markVerified($s, 'domain', null));
    }

    private function markVerified(Sender $sender, string $method, ?User $actor): void
    {
        $sender->update(['verified_at' => now(), 'verification_method' => $method]);
        $this->audit->record('sender.verified', $sender, $actor, null, ['method' => $method]);
        SenderVerified::dispatch($sender->id);
    }

    private function makeDefault(Sender $sender): void
    {
        Sender::query()->where('id', '!=', $sender->id)->where('is_default', true)->update(['is_default' => false]);
        $sender->update(['is_default' => true]);
    }

    private function sendVerification(Sender $sender): void
    {
        $url = URL::temporarySignedRoute('senders.verify', now()->addHours(48), ['id' => $sender->uuid]);
        $sender->update(['verification_sent_at' => now()]);
        Mail::to($sender->email)->queue(new SenderVerificationMail($sender, $url));
    }

    private function usedByActiveCampaign(Sender $sender): bool
    {
        // `campaigns.sender_id` is added by Slot B (WP-42); guard until it exists.
        if (! Schema::hasColumn('campaigns', 'sender_id')) {
            return false;
        }

        return DB::table('campaigns')->where('sender_id', $sender->id)
            ->whereIn('status', ['scheduled', 'sending', 'running'])->exists();
    }
}
