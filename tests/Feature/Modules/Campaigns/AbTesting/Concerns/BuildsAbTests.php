<?php

namespace Tests\Feature\Modules\Campaigns\AbTesting\Concerns;

use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;

/**
 * WP-80 fixtures, on top of {@see BuildsCampaignFixtures}.
 */
trait BuildsAbTests
{
    /**
     * @return array<string, mixed>
     */
    protected function abConfig(array $overrides = []): array
    {
        return [
            'type' => 'subject',
            'sample_percent' => 20,
            'winner_metric' => 'opens',
            'test_duration_minutes' => 240,
            'auto_send_winner' => true,
            ...$overrides,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function abVariants(string $subjectA = 'Objet A', string $subjectB = 'Objet B'): array
    {
        return [
            ['label' => 'A', 'subject' => $subjectA, 'preview_text' => 'Aperçu A'],
            ['label' => 'B', 'subject' => $subjectB],
        ];
    }

    /**
     * Draft A/B subject-test campaign targeting a list of `$count` recipients.
     *
     * @param  array<string, mixed>  $config
     * @param  list<array<string, mixed>>|null  $variants
     */
    protected function abCampaign(User $owner, int $count = 20, array $config = [], ?array $variants = null): Campaign
    {
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients($count))->id]]);

        return app(AbTestService::class)->save($campaign, true, $this->abConfig($config), $variants ?? $this->abVariants());
    }
}
