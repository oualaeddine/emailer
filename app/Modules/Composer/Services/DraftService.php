<?php

namespace App\Modules\Composer\Services;

use App\Modules\Composer\Models\Draft;
use App\Modules\Composer\Models\DraftVersion;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * docs/11-email-composer.md §11.6 — Draft Autosave & Version History.
 */
class DraftService
{
    public function paginateForUser(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return Draft::query()->where('user_id', $user->id)->orderByDesc('updated_at')->paginate($perPage);
    }

    public function create(User $user, ?string $subject = null, ?string $htmlBody = null): Draft
    {
        return Draft::query()->create([
            'user_id' => $user->id,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'status' => \App\Domain\Enums\DraftStatus::Draft->value,
        ]);
    }

    /**
     * Autosave: plain update, no version created (docs/11-email-composer.md §11.6 —
     * versions are only created on an explicit save checkpoint).
     */
    public function autosave(Draft $draft, ?string $subject, ?string $htmlBody, ?int $signatureId): Draft
    {
        $draft->fill(array_filter([
            'subject' => $subject,
            'html_body' => $htmlBody,
            'signature_id' => $signatureId,
        ], fn ($v) => $v !== null));
        $draft->save();

        return $draft;
    }

    public function saveVersion(Draft $draft, User $author): DraftVersion
    {
        return DB::transaction(function () use ($draft, $author): DraftVersion {
            $nextVersion = ($draft->versions()->max('version_number') ?? 0) + 1;

            return DraftVersion::query()->create([
                'draft_id' => $draft->id,
                'version_number' => $nextVersion,
                'html_body' => $draft->html_body,
                'subject' => $draft->subject,
                'created_by' => $author->id,
            ]);
        });
    }

    /**
     * docs/11-email-composer.md §11.6 — restoring copies a prior version's
     * content back into the working draft, creating a NEW version rather
     * than mutating history.
     */
    public function restoreVersion(Draft $draft, DraftVersion $version, User $author): Draft
    {
        return DB::transaction(function () use ($draft, $version, $author): Draft {
            $draft->update(['subject' => $version->subject, 'html_body' => $version->html_body]);
            $this->saveVersion($draft, $author);

            return $draft->fresh();
        });
    }
}
