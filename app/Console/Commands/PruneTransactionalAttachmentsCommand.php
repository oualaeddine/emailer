<?php

namespace App\Console\Commands;

use App\Domain\Enums\MessageStatus;
use App\Modules\Tracking\Models\Message;
use App\Modules\Transactional\Services\TransactionalAttachmentStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes `transactional-attachments/{uuid}/` folders once every message that
 * references them is in a final state (anything but queued / sending) and older
 * than the retention period, and clears `delivery_options.attachments`.
 */
class PruneTransactionalAttachmentsCommand extends Command
{
    protected $signature = 'transactional:prune-attachments {--days= : Retention in days (default: config transactional.attachment_retention_days)}';

    protected $description = 'Delete stored transactional attachments of old messages in a final state';

    public function handle(): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('transactional.attachment_retention_days', 30);
        $cutoff = now()->subDays(max(0, $days));
        $pending = [MessageStatus::Queued->value, MessageStatus::Sending->value];
        $disk = Storage::disk(TransactionalAttachmentStore::DISK);

        // Folders are named after the first message's uuid but shared by the whole request: group by folder.
        $folders = [];
        Message::query()->whereNotNull('delivery_options')->orderBy('id')->chunkById(500, function ($messages) use (&$folders): void {
            foreach ($messages as $message) {
                foreach ((array) ($message->delivery_options['attachments'] ?? []) as $file) {
                    $folder = dirname((string) ($file['path'] ?? ''));
                    if (str_starts_with($folder, TransactionalAttachmentStore::DIRECTORY.'/')) {
                        $folders[$folder][$message->id] = true;
                    }
                }
            }
        });

        $deleted = 0;
        foreach ($folders as $folder => $ids) {
            $ids = array_keys($ids);
            $blocked = Message::query()->whereIn('id', $ids)
                ->where(fn ($q) => $q->whereIn('status', $pending)->orWhere('created_at', '>', $cutoff))
                ->exists();
            if ($blocked) {
                continue;
            }

            $disk->deleteDirectory($folder);
            $deleted++;

            Message::query()->whereIn('id', $ids)->each(function (Message $message) use ($folder): void {
                $options = (array) $message->delivery_options;
                $options['attachments'] = array_values(array_filter(
                    (array) ($options['attachments'] ?? []),
                    fn ($f): bool => dirname((string) ($f['path'] ?? '')) !== $folder,
                ));
                if ($options['attachments'] === []) {
                    unset($options['attachments']);
                }
                $message->forceFill(['delivery_options' => $options !== [] ? $options : null])->save();
            });
        }

        $this->info("Pruned {$deleted} attachment folder(s).");

        return self::SUCCESS;
    }
}
