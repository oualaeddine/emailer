<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Modules\Suppression\Listeners\SuppressionListenerServiceProvider;
use App\Modules\Recipients\Lists\ListsServiceProvider;
use App\Modules\Templates\TemplatesServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    HorizonServiceProvider::class,
    SuppressionListenerServiceProvider::class,
    ListsServiceProvider::class,
    TemplatesServiceProvider::class,
    \App\Modules\Webhooks\WebhooksServiceProvider::class,
];
