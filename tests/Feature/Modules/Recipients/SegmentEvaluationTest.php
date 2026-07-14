<?php

namespace Tests\Feature\Modules\Recipients;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\Tag;
use App\Modules\Recipients\Services\SegmentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/13-recipient-management.md §13.7 — Segment rule tree evaluation.
 * docs/23-suppression-list.md §23.6 — suppressed recipients are always
 * excluded regardless of the rule tree (non-overridable safety rule).
 */
class SegmentEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_simple_equals_rule_filters_by_status(): void
    {
        Recipient::factory()->create(['status' => RecipientStatus::Active->value, 'company_name' => 'Alpha']);
        Recipient::factory()->create(['status' => RecipientStatus::Invalid->value, 'company_name' => 'Beta']);

        $service = app(SegmentEvaluationService::class);
        $results = $service->compile([
            'operator' => 'AND',
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => RecipientStatus::Active->value],
            ],
        ])->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results->first()->company_name);
    }

    public function test_suppressed_recipients_are_always_excluded_regardless_of_rules(): void
    {
        Recipient::factory()->suppressed()->create(['company_name' => 'Suppressed Co']);
        Recipient::factory()->create(['status' => RecipientStatus::Active->value, 'company_name' => 'Active Co']);

        $service = app(SegmentEvaluationService::class);

        // A rule tree with NO status condition at all should still exclude
        // the suppressed recipient.
        $results = $service->compile([
            'operator' => 'AND',
            'conditions' => [
                ['field' => 'source', 'operator' => 'equals', 'value' => 'manual'],
            ],
        ])->get();

        $this->assertTrue($results->pluck('company_name')->doesntContain('Suppressed Co'));
    }

    public function test_tags_includes_any_operator(): void
    {
        $vip = Tag::query()->create(['name' => 'vip', 'color' => 'brand']);
        $recipientWithTag = Recipient::factory()->create(['company_name' => 'Tagged']);
        $recipientWithTag->tags()->attach($vip);
        Recipient::factory()->create(['company_name' => 'Untagged']);

        $service = app(SegmentEvaluationService::class);
        $results = $service->compile([
            'operator' => 'AND',
            'conditions' => [
                ['field' => 'tags', 'operator' => 'includes_any', 'value' => ['vip']],
            ],
        ])->get();

        $this->assertCount(1, $results);
        $this->assertSame('Tagged', $results->first()->company_name);
    }

    public function test_nested_or_group_within_an_and_condition(): void
    {
        Recipient::factory()->create(['company_name' => 'Match A', 'status' => RecipientStatus::Active->value, 'custom_fields' => ['industry' => 'retail']]);
        Recipient::factory()->create(['company_name' => 'Match B', 'status' => RecipientStatus::Active->value, 'custom_fields' => ['industry' => 'tech']]);
        Recipient::factory()->create(['company_name' => 'No Match', 'status' => RecipientStatus::Active->value, 'custom_fields' => ['industry' => 'finance']]);

        $service = app(SegmentEvaluationService::class);
        $results = $service->compile([
            'operator' => 'AND',
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => RecipientStatus::Active->value],
                [
                    'operator' => 'OR',
                    'conditions' => [
                        ['field' => 'custom_fields.industry', 'operator' => 'equals', 'value' => 'retail'],
                        ['field' => 'custom_fields.industry', 'operator' => 'equals', 'value' => 'tech'],
                    ],
                ],
            ],
        ])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['Match A', 'Match B'], $results->pluck('company_name')->all());
    }
}
