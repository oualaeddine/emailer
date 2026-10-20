<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Enums\AutomationStepType;
use Illuminate\Contracts\Container\Container;

class StepExecutorRegistry
{
    /** @var array<string, class-string<StepExecutor>> */
    private const EXECUTORS = [
        'send_email' => SendEmailExecutor::class,
        'wait' => WaitExecutor::class,
        'condition' => ConditionExecutor::class,
        'add_to_list' => AddToListExecutor::class,
        'remove_from_list' => RemoveFromListExecutor::class,
        'update_attribute' => UpdateAttributeExecutor::class,
        'end' => EndExecutor::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(string $type): StepExecutor
    {
        $class = self::EXECUTORS[AutomationStepType::from($type)->value];

        return $this->container->make($class);
    }
}
