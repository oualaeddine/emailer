<?php

namespace Tests\Feature\Modules\Campaigns\Concerns;

use App\Domain\Enums\RecipientListType;
use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use Illuminate\Support\Collection;

/**
 * Shared fixtures for the WP-42 campaign v2 tests.
 */
trait BuildsCampaignFixtures
{
    protected function operator(): User
    {
        return User::factory()->withRole(RoleName::MarketingOperator)->create();
    }

    protected function manager(): User
    {
        return User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    /**
     * @param  iterable<Recipient>  $recipients
     */
    protected function listWith(iterable $recipients, string $name = 'Liste'): RecipientList
    {
        $list = RecipientList::query()->create(['name' => $name, 'type' => RecipientListType::Static->value]);
        $list->recipients()->syncWithoutDetaching(
            collect($recipients)->mapWithKeys(fn (Recipient $r) => [$r->id => ['added_at' => now()]])->all(),
        );

        return $list;
    }

    /**
     * @return Collection<int, Recipient>
     */
    protected function recipients(int $count, array $attributes = []): Collection
    {
        return Recipient::factory()->count($count)->create($attributes);
    }

    /**
     * Segment matching `company_name = $company`.
     */
    protected function segmentForCompany(string $company, string $name = 'Segment'): Segment
    {
        $list = RecipientList::query()->create(['name' => $name, 'type' => RecipientListType::DynamicSegment->value]);

        return Segment::query()->create([
            'recipient_list_id' => $list->id,
            'rules' => ['operator' => 'AND', 'conditions' => [
                ['field' => 'company_name', 'operator' => 'equals', 'value' => $company],
            ]],
        ]);
    }

    /**
     * @param  array{lists?: list<int>, exclusion_lists?: list<int>, segments?: list<int>, excluded_segments?: list<int>}  $audience
     * @param  array<string, mixed>  $attributes
     */
    protected function campaignWithAudience(User $owner, array $audience, array $attributes = []): Campaign
    {
        return app(CampaignService::class)->create([
            'name' => 'Campagne '.uniqid(),
            'subject' => 'Objet',
            'html_body' => '<p>Bonjour {{recipient.first_name|there}}</p>',
            'audience' => $audience,
            ...$attributes,
        ], $owner);
    }
}
