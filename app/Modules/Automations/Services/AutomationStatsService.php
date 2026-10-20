<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Enums\AutomationStepOutcome;
use App\Modules\Automations\Enums\AutomationStepType;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.7.5 — `Automation.stats`, `AutomationStep.stats` and the
 * "Activité" tab (`AutomationActivity`: run counters + per-e-mail
 * BrevoStats / RateSet computed from `messages` and `message_events`).
 * `exited` counts runs that left early (manual exit, deleted contact or
 * step) and failed runs.
 */
class AutomationStatsService
{
    /**
     * @param  list<int>  $automationIds
     * @return array<int, array{started: int, in_progress: int, completed: int, exited: int}>
     */
    public function runCounts(array $automationIds, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = DB::table('automation_runs')
            ->whereIn('automation_id', $automationIds ?: [0])
            ->when($from, fn ($q) => $q->where('started_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('started_at', '<=', $to))
            ->groupBy('automation_id', 'status')
            ->select('automation_id', 'status', DB::raw('COUNT(*) as aggregate'))
            ->get();

        $counts = [];
        foreach ($automationIds as $id) {
            $counts[$id] = ['started' => 0, 'in_progress' => 0, 'completed' => 0, 'exited' => 0];
        }

        foreach ($rows as $row) {
            $id = (int) $row->automation_id;
            $n = (int) $row->aggregate;
            $counts[$id]['started'] += $n;
            match ($row->status) {
                'active', 'waiting' => $counts[$id]['in_progress'] += $n,
                'completed' => $counts[$id]['completed'] += $n,
                default => $counts[$id]['exited'] += $n,
            };
        }

        return $counts;
    }

    /** @return array<int, array{entered: int, completed: int}> step id => stats */
    public function stepStats(Automation $automation): array
    {
        $stats = [];

        $logs = DB::table('automation_step_logs as l')
            ->join('automation_steps as s', 's.id', '=', 'l.automation_step_id')
            ->where('s.automation_id', $automation->id)
            ->groupBy('l.automation_step_id')
            ->select('l.automation_step_id', DB::raw('COUNT(*) as entered'),
                DB::raw("SUM(CASE WHEN l.outcome <> '".AutomationStepOutcome::Failed->value."' THEN 1 ELSE 0 END) as completed"))
            ->get();

        foreach ($logs as $row) {
            $stats[(int) $row->automation_step_id] = ['entered' => (int) $row->entered, 'completed' => (int) $row->completed];
        }

        $current = DB::table('automation_runs')
            ->where('automation_id', $automation->id)
            // in progress on the step, or left the automation while on it (manual exit)
            ->whereIn('status', ['active', 'waiting', 'exited'])
            ->whereNotNull('current_step_id')
            ->groupBy('current_step_id')
            ->select('current_step_id', DB::raw('COUNT(*) as aggregate'))
            ->get();

        foreach ($current as $row) {
            $id = (int) $row->current_step_id;
            $stats[$id] ??= ['entered' => 0, 'completed' => 0];
            $stats[$id]['entered'] += (int) $row->aggregate;
        }

        return $stats;
    }

    /** @return array<string, mixed> AutomationActivity */
    public function activity(Automation $automation, ?Carbon $from, ?Carbon $to): array
    {
        $counts = $this->runCounts([$automation->id], $from, $to)[$automation->id];

        $steps = app(AutomationService::class)->toInputTree($automation);
        $emails = [];
        $this->collectEmailSteps($steps, $emails);

        $stepRows = AutomationStep::query()->whereIn('uuid', array_column($emails, 'id') ?: ['-'])->get()->keyBy('uuid');
        $templates = Template::withTrashed()->whereIn('uuid', array_filter(array_column($emails, 'template_id')) ?: ['-'])->get()->keyBy('uuid');

        $result = [];
        foreach ($emails as $email) {
            $step = $stepRows->get($email['id']);
            if ($step === null) {
                continue;
            }

            $stats = $this->emailStats($step->id, $from, $to);
            $result[] = [
                'step_id' => $step->uuid,
                'template_name' => (string) ($templates->get((string) $email['template_id'])?->name ?? ''),
                'globalStats' => $stats,
                'rates' => self::rates($stats),
            ];
        }

        return $counts + ['emails' => $result];
    }

    /** @return array<string, int|float> BrevoStats */
    public function emailStats(int $stepId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $empty = [
            'sent' => 0, 'delivered' => 0, 'hardBounces' => 0, 'softBounces' => 0, 'complaints' => 0,
            'unsubscriptions' => 0, 'viewed' => 0, 'uniqueViews' => 0, 'trackableViews' => 0,
            'estimatedViews' => 0, 'appleMppOpens' => 0, 'uniqueClicks' => 0, 'clickers' => 0,
            'deferred' => 0, 'returnBounce' => 0, 'opensRate' => 0.0,
        ];

        if (! Schema::hasColumn('messages', 'automation_step_id')) {
            return $empty;
        }

        $messages = fn () => DB::table('messages as m')
            ->where('m.automation_step_id', $stepId)
            ->where('m.is_test', false)
            ->when($from, fn ($q) => $q->where('m.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('m.created_at', '<=', $to));

        $row = $messages()->selectRaw(
            'SUM(CASE WHEN m.sent_at IS NOT NULL OR m.delivered_at IS NOT NULL THEN 1 ELSE 0 END) as sent, '
            .'SUM(CASE WHEN '.Message::deliveredSql('m').' THEN 1 ELSE 0 END) as delivered, '
            ."SUM(CASE WHEN m.status = 'hard_bounced' THEN 1 ELSE 0 END) as hard, "
            ."SUM(CASE WHEN m.status = 'soft_bounced' THEN 1 ELSE 0 END) as soft"
        )->first();

        $events = $messages()
            ->join('message_events as e', 'e.message_id', '=', 'm.id')
            ->groupBy('e.event_type')
            ->select('e.event_type', DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT e.message_id) as uniq'))
            ->get()
            ->keyBy('event_type');

        $trackable = (int) $messages()
            ->join('message_events as e', 'e.message_id', '=', 'm.id')
            ->where('e.event_type', 'opened')
            ->where('e.is_proxy_open', false)
            ->distinct()
            ->count('e.message_id');

        $uniq = fn (string $type): int => (int) ($events->get($type)?->uniq ?? 0);
        $delivered = (int) ($row->delivered ?? 0);
        $uniqueViews = $uniq('opened');

        return array_merge($empty, [
            'sent' => (int) ($row->sent ?? 0),
            'delivered' => $delivered,
            'hardBounces' => max((int) ($row->hard ?? 0), $uniq('hard_bounced')),
            'softBounces' => max((int) ($row->soft ?? 0), $uniq('soft_bounced')),
            'complaints' => $uniq('spam_complaint'),
            'unsubscriptions' => $uniq('unsubscribed'),
            'viewed' => (int) ($events->get('opened')?->total ?? 0),
            'uniqueViews' => $uniqueViews,
            'trackableViews' => $trackable,
            'estimatedViews' => $uniqueViews,
            'appleMppOpens' => max(0, $uniqueViews - $trackable),
            'uniqueClicks' => $uniq('clicked'),
            'clickers' => $uniq('clicked'),
            'deferred' => $uniq('deferred'),
            'opensRate' => $delivered > 0 ? round($uniqueViews / $delivered * 100, 2) : 0.0,
        ]);
    }

    /**
     * @param  array<string, int|float>  $s  BrevoStats
     * @return array<string, float> RateSet (ratios 0–1, denominator delivered except delivery_rate)
     */
    public static function rates(array $s): array
    {
        $ratio = fn (int|float $n, int|float $d): float => $d > 0 ? round($n / $d, 4) : 0.0;

        return [
            'delivery_rate' => $ratio($s['delivered'], $s['sent']),
            'open_rate' => $ratio($s['uniqueViews'], $s['delivered']),
            'click_rate' => $ratio($s['uniqueClicks'], $s['delivered']),
            'click_to_open_rate' => $ratio($s['uniqueClicks'], $s['uniqueViews']),
            'unsubscribe_rate' => $ratio($s['unsubscriptions'], $s['delivered']),
            'bounce_rate' => $ratio($s['hardBounces'] + $s['softBounces'], $s['delivered']),
            'complaint_rate' => $ratio($s['complaints'], $s['delivered']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array{id: string, template_id: mixed}>  $out
     */
    private function collectEmailSteps(array $nodes, array &$out): void
    {
        foreach ($nodes as $node) {
            if ($node['type'] === AutomationStepType::SendEmail->value) {
                $out[] = ['id' => $node['id'], 'template_id' => $node['config']['template_id'] ?? null];
            }
            $this->collectEmailSteps($node['yes'] ?? [], $out);
            $this->collectEmailSteps($node['no'] ?? [], $out);
        }
    }
}
