<?php

namespace App\Modules\Recipients\Attributes\Jobs;

use App\Modules\Recipients\Models\Recipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/** docs/45 §45.5.1 — removes a deleted attribute's values from `recipients.custom_fields`. */
class PurgeAttributeValuesJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public readonly string $name) {}

    public function handle(): void
    {
        Recipient::withTrashed()
            ->whereNotNull('custom_fields->'.$this->name)
            ->chunkById(500, function ($chunk): void {
                foreach ($chunk as $recipient) {
                    $fields = $recipient->custom_fields ?? [];
                    unset($fields[$this->name]);
                    $recipient->custom_fields = $fields;
                    $recipient->saveQuietly();
                }
            });
    }
}
