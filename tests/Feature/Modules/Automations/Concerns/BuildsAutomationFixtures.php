<?php

namespace Tests\Feature\Modules\Automations\Concerns;

use App\Domain\Enums\RoleName;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Automations\Services\AutomationService;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Templates\Models\Template;
use Database\Factories\RecipientListFactory;

trait BuildsAutomationFixtures
{
    protected function manager(): User
    {
        return User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    protected function operator(): User
    {
        return User::factory()->withRole(RoleName::MarketingOperator)->create();
    }

    protected function list(string $name = 'Liste'): RecipientList
    {
        return RecipientListFactory::new()->create(['name' => $name.' '.fake()->unique()->numberBetween(1, 1_000_000)]);
    }

    protected function contact(array $attributes = []): Recipient
    {
        return Recipient::factory()->create($attributes);
    }

    protected function template(string $name = 'Bienvenue J+0'): Template
    {
        return Template::factory()->create([
            'name' => $name,
            'subject' => 'Bienvenue {{recipient.first_name}}',
            'html_content' => '<html><body><p>Bonjour {{recipient.first_name}}</p></body></html>',
        ]);
    }

    /** @return array<string, mixed> */
    protected function sendEmailStep(Template $template, array $extra = []): array
    {
        return array_replace(['type' => 'send_email', 'position' => 0, 'config' => ['template_id' => $template->uuid, 'subject' => null, 'sender_id' => null, 'preview_text' => null], 'yes' => [], 'no' => []], $extra);
    }

    /** @return array<string, mixed> */
    protected function waitStep(int $amount, string $unit = 'days'): array
    {
        return ['type' => 'wait', 'position' => 0, 'config' => ['amount' => $amount, 'unit' => $unit], 'yes' => [], 'no' => []];
    }

    /** @return array<string, mixed> */
    protected function step(string $type, array $config = [], array $yes = [], array $no = []): array
    {
        return ['type' => $type, 'position' => 0, 'config' => $config, 'yes' => $yes, 'no' => $no];
    }

    /**
     * Active automation with the given trigger and step tree (positions = order).
     *
     * @param  array<string, mixed>  $config
     * @param  list<array<string, mixed>>  $steps
     */
    protected function activeAutomation(string $triggerType, array $config, array $steps, array $attributes = []): Automation
    {
        $automation = Automation::factory()->trigger($triggerType, $config)->active()->create($attributes);
        $this->setSteps($automation, $steps);

        return $automation->refresh();
    }

    /** @param  list<array<string, mixed>>  $steps */
    protected function setSteps(Automation $automation, array $steps): void
    {
        $positioned = $this->positioned($steps);
        $status = $automation->status;
        $automation->update(['status' => 'draft']);
        app(AutomationService::class)->replaceSteps($automation, $positioned);
        $automation->update(['status' => $status]);
    }

    protected function stepAt(Automation $automation, int $index, ?AutomationStep $parent = null, ?string $branch = null): AutomationStep
    {
        return AutomationStep::query()
            ->where('automation_id', $automation->id)
            ->where('parent_step_id', $parent?->id)
            ->where('branch', $branch)
            ->orderBy('position')
            ->get()[$index];
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    private function positioned(array $steps): array
    {
        foreach ($steps as $i => $step) {
            $steps[$i]['position'] = $i;
            $steps[$i]['yes'] = $this->positioned($step['yes'] ?? []);
            $steps[$i]['no'] = $this->positioned($step['no'] ?? []);
        }

        return $steps;
    }
}
