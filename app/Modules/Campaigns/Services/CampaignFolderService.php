<?php

namespace App\Modules\Campaigns\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignFolder;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — campaign folders CRUD (`Folder`, kind `campaign`).
 */
class CampaignFolderService
{
    /**
     * @return Collection<int, CampaignFolder>
     */
    public function all(): Collection
    {
        return CampaignFolder::query()->withCount('campaigns')->orderBy('name')->get();
    }

    public function create(string $name, ?User $createdBy): CampaignFolder
    {
        $folder = CampaignFolder::query()->create(['name' => $name, 'created_by' => $createdBy?->id]);

        return $folder->loadCount('campaigns');
    }

    public function rename(CampaignFolder $folder, string $name): CampaignFolder
    {
        $folder->update(['name' => $name]);

        return $folder->loadCount('campaigns');
    }

    /**
     * Campaigns inside move to "no folder".
     */
    public function delete(CampaignFolder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            Campaign::query()->where('folder_id', $folder->id)->update(['folder_id' => null]);
            $folder->delete();
        });
    }
}
