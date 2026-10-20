<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Enums\AutomationStepOutcome;
use Illuminate\Support\Carbon;

/** What the runner does after a step executed. */
final class StepResult
{
    public const NEXT = 'next';

    public const BRANCH = 'branch';

    public const WAIT = 'wait';

    public const END = 'end';

    private function __construct(
        public readonly string $kind,
        public readonly AutomationStepOutcome $outcome,
        public readonly ?string $branch = null,
        public readonly ?Carbon $resumeAt = null,
        public readonly ?int $messageId = null,
    ) {}

    public static function next(AutomationStepOutcome $outcome = AutomationStepOutcome::Completed, ?int $messageId = null): self
    {
        return new self(self::NEXT, $outcome, messageId: $messageId);
    }

    public static function branch(bool $yes): self
    {
        return new self(self::BRANCH, $yes ? AutomationStepOutcome::Yes : AutomationStepOutcome::No, $yes ? 'yes' : 'no');
    }

    public static function wait(Carbon $resumeAt): self
    {
        return new self(self::WAIT, AutomationStepOutcome::Completed, resumeAt: $resumeAt);
    }

    public static function end(): self
    {
        return new self(self::END, AutomationStepOutcome::Completed);
    }
}
