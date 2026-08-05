<?php

/*
|--------------------------------------------------------------------------
| Queue Topology & Supervisor Deployment Notes — docs/24-queue-management.md
|--------------------------------------------------------------------------
|
| docs/24-queue-management.md §24.1 defines the named-queue topology this
| platform relies on. Each queue is a distinct Redis list so a backlog in
| one workload can never starve another; priority between them is
| established purely by the order queues are listed in a worker's
| `--queue=` argument (§24.2), which is why every supervisor below lists
| its queue(s) that way even when it only serves one.
|
| | Queue                | Purpose                                             | Priority |
| |-----------------------|-----------------------------------------------------|----------|
| | smtp-send-high        | Transactional (non-campaign) message sends           | Highest  |
| | smtp-send-campaign    | Campaign message sends (chunked dispatch)             | Normal   |
| | tracking-webhooks     | Inbound provider webhook processing                   | High     |
| | imports               | CSV/Excel/PageJaunes import processing                | Normal   |
| | notifications         | Internal admin notifications                          | Normal   |
| | reporting             | Materialized view refresh, snapshot generation        | Low      |
| | maintenance           | File pruning, health probes, quota reconciliation     | Low      |
| | default               | Anything not explicitly routed                        | Lowest   |
|
| `smtp-send-*` supervisors get `balance => auto` and the largest process
| ceiling since send throughput is the platform's primary workload
| (§24.1). Retries are not a separate physical queue — failed attempts
| use `release($delay)` back onto their originating queue (§24.3); the
| Dead-Letter Queue is simply the `failed_jobs` table plus
| `DeadLetterHandler` (§24.4), not a Horizon queue either.
|
| Deployment: run one long-lived `php artisan horizon` process per app
| server under a process supervisor (systemd unit or `horizon` inside
| Supervisor/Deployer), *not* `queue:work` directly — Horizon owns
| spawning/scaling the worker processes described below itself. Restart
| it (`php artisan horizon:terminate`, which exits gracefully after the
| current job) on every deploy so workers pick up new code; Horizon
| re-spawns automatically under its process supervisor.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. This includes the
    | queue's job metrics, process configurations, and metrics data.
    |
    */

    'use' => env('HORIZON_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env('HORIZON_PREFIX', 'horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    | docs/24-queue-management.md §24.5 — the raw Horizon dashboard
    | (`/horizon`) is restricted to Administrator only, distinct from the
    | in-app Queue Monitoring page which is also readable by Marketing
    | Manager (§24.7). `HorizonAdminAccess` mirrors the `Gate::authorize`
    | check used by `QueueStatsController` (queues.view) but additionally
    | requires the Administrator role, matching the dashboard's narrower
    | audience — implemented alongside the real `App\Providers\HorizonServiceProvider`
    | once `laravel/horizon` is installed (out of scope for this work
    | package; see composer.json note).
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    | `smtp-send-high` and `tracking-webhooks` get the tightest thresholds
    | since low latency is what their queue exists for (§24.1); background
    | workloads (`reporting`, `maintenance`) tolerate a much longer wait.
    |
    */

    'waits' => [
        'redis:smtp-send-high' => 30,
        'redis:smtp-send-campaign' => 120,
        'redis:tracking-webhooks' => 60,
        'redis:imports' => 300,
        'redis:notifications' => 300,
        'redis:reporting' => 900,
        'redis:maintenance' => 900,
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the
    | list of completed jobs within the Horizon dashboard. This setting
    | may be used to fully silence any noisy jobs you're not interested in.
    |
    */

    'silenced' => [
        // None silenced — every queue listed in §24.1 is operationally
        // significant enough to want visibility on.
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | 5 minute snapshot rate.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all workers to gracefully terminate unless the --wait
    | option is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last instance
    | will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Queue supervisors, per §24.1's topology. `balance => auto` on the
    | `smtp-send-*` supervisors lets Horizon scale their process count up
    | and down within `minProcesses`/`maxProcesses` based on measured
    | wait time, since send throughput is the platform's primary
    | workload; every other supervisor uses `simple` balancing with a
    | fixed process count sized to its priority (§24.1).
    |
    */

    'defaults' => [
        'supervisor-transactional' => [
            'connection' => 'redis',
            'queue' => ['smtp-send-high'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 20,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-campaigns' => [
            'connection' => 'redis',
            'queue' => ['smtp-send-campaign'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-tracking-webhooks' => [
            'connection' => 'redis',
            'queue' => ['tracking-webhooks'],
            'balance' => 'simple',
            'maxProcesses' => 6,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 30,
            'nice' => 0,
        ],
        'supervisor-imports' => [
            'connection' => 'redis',
            'queue' => ['imports'],
            'balance' => 'simple',
            'maxProcesses' => 4,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 600,
            'nice' => 0,
        ],
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-reporting' => [
            'connection' => 'redis',
            'queue' => ['reporting'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 900,
            'nice' => 10,
        ],
        'supervisor-maintenance' => [
            'connection' => 'redis',
            'queue' => ['maintenance', 'default'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 900,
            'nice' => 10,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-transactional' => [
                'minProcesses' => 2,
                'maxProcesses' => 20,
                'balanceMaxShift' => 4,
                'balanceCooldown' => 3,
            ],
            'supervisor-campaigns' => [
                'minProcesses' => 1,
                'maxProcesses' => 10,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'supervisor-tracking-webhooks' => [
                'maxProcesses' => 6,
            ],
            'supervisor-imports' => [
                'maxProcesses' => 4,
            ],
            'supervisor-notifications' => [
                'maxProcesses' => 2,
            ],
            'supervisor-reporting' => [
                'maxProcesses' => 2,
            ],
            'supervisor-maintenance' => [
                'maxProcesses' => 2,
            ],
        ],

        'staging' => [
            'supervisor-transactional' => [
                'minProcesses' => 1,
                'maxProcesses' => 6,
            ],
            'supervisor-campaigns' => [
                'minProcesses' => 1,
                'maxProcesses' => 4,
            ],
            'supervisor-tracking-webhooks' => [
                'maxProcesses' => 2,
            ],
            'supervisor-imports' => [
                'maxProcesses' => 2,
            ],
            'supervisor-notifications' => [
                'maxProcesses' => 1,
            ],
            'supervisor-reporting' => [
                'maxProcesses' => 1,
            ],
            'supervisor-maintenance' => [
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'supervisor-transactional' => [
                'maxProcesses' => 2,
            ],
            'supervisor-campaigns' => [
                'maxProcesses' => 1,
            ],
            'supervisor-tracking-webhooks' => [
                'maxProcesses' => 1,
            ],
            'supervisor-imports' => [
                'maxProcesses' => 1,
            ],
            'supervisor-notifications' => [
                'maxProcesses' => 1,
            ],
            'supervisor-reporting' => [
                'maxProcesses' => 1,
            ],
            'supervisor-maintenance' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

];
