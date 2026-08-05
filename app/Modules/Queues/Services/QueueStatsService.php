<?php

namespace App\Modules\Queues\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * docs/24-queue-management.md §24.6 — Advanced Queue Monitoring Dashboard.
 *
 * Reads whatever the underlying queue connection and the `failed_jobs`
 * table can tell us directly, rather than depending on Horizon's own
 * `MetricsRepository`/supervisor status API — so the in-app Queue
 * Monitoring endpoint keeps returning a real (if reduced) snapshot even
 * in environments where Horizon isn't installed/running (e.g. this
 * repository until `composer require laravel/horizon` has actually been
 * run — see composer.json).
 */
class QueueStatsService
{
    /**
     * docs/24-queue-management.md §24.1 — canonical named-queue topology,
     * highest priority first. Mirrored here instead of read back out of
     * config/horizon.php so this list stays valid even when the Horizon
     * package (and therefore anything that might depend on it being
     * booted) is absent.
     *
     * @var list<string>
     */
    public const QUEUES = [
        'smtp-send-high',
        'tracking-webhooks',
        'smtp-send-campaign',
        'imports',
        'notifications',
        'reporting',
        'maintenance',
        'default',
    ];

    private const RECENT_FAILED_LIMIT = 10;

    /**
     * @return array{
     *     horizon_available: bool,
     *     queues: list<array{name: string, size: int}>,
     *     failed_jobs: array{
     *         count: int,
     *         recent: list<array{id: string, queue: string, connection: string, exception: string, failed_at: string|null}>,
     *     },
     * }
     */
    public function snapshot(): array
    {
        return [
            'horizon_available' => $this->horizonAvailable(),
            'queues' => $this->queueSizes(),
            'failed_jobs' => $this->failedJobsSummary(),
        ];
    }

    /**
     * Horizon's own metrics/supervisor APIs are intentionally not called
     * here — only its presence is reported — so this service never
     * throws just because the package is missing (see class docblock).
     */
    private function horizonAvailable(): bool
    {
        return class_exists(\Laravel\Horizon\Horizon::class);
    }

    /**
     * @return list<array{name: string, size: int}>
     */
    private function queueSizes(): array
    {
        return array_map(
            static fn (string $queue): array => [
                'name' => $queue,
                'size' => Queue::size($queue),
            ],
            self::QUEUES,
        );
    }

    /**
     * @return array{count: int, recent: list<array{id: string, queue: string, connection: string, exception: string, failed_at: string|null}>}
     */
    private function failedJobsSummary(): array
    {
        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(self::RECENT_FAILED_LIMIT)
            ->get(['uuid', 'connection', 'queue', 'exception', 'failed_at']);

        return [
            'count' => (int) DB::table('failed_jobs')->count(),
            'recent' => $recent
                ->map(static fn (object $row): array => [
                    'id' => (string) $row->uuid,
                    'queue' => (string) $row->queue,
                    'connection' => (string) $row->connection,
                    // Exceptions can be enormous stack traces; the dashboard
                    // widget (§24.6) only needs enough to identify the
                    // failure, full detail stays in the `failed_jobs` row.
                    'exception' => Str::limit((string) $row->exception, 500),
                    'failed_at' => $row->failed_at !== null ? (string) $row->failed_at : null,
                ])
                ->all(),
        ];
    }
}
