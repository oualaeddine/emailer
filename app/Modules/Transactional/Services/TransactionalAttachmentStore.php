<?php

namespace App\Modules\Transactional\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * next-sender — attachments of `POST /transactional/send` (docs/45 §45.7.6).
 * Only the inline base64 `content` form is accepted; `url` attachments are
 * rejected (422) rather than fetched server-side. Files are written to the
 * private `local` disk under `transactional-attachments/{message uuid}/`.
 */
class TransactionalAttachmentStore
{
    public const DISK = 'local';

    public const DIRECTORY = 'transactional-attachments';

    public const MAX_TOTAL_BYTES = 10 * 1024 * 1024;

    /**
     * Validates and decodes the request's attachments.
     *
     * @param  array<int, mixed>  $attachments
     * @return list<array{name: string, bytes: string, mime: string}>
     *
     * @throws ValidationException
     */
    public function decode(array $attachments): array
    {
        $decoded = [];
        $total = 0;

        foreach (array_values($attachments) as $i => $attachment) {
            $attachment = is_array($attachment) ? $attachment : [];

            if (filled($attachment['url'] ?? null)) {
                throw ValidationException::withMessages([
                    "attachments.{$i}.url" => 'Les pièces jointes par URL ne sont pas prises en charge : fournissez le contenu du fichier encodé en base64 (champ content).',
                ]);
            }

            $content = preg_replace('/\s+/', '', (string) ($attachment['content'] ?? '')) ?? '';
            $bytes = $content !== '' ? base64_decode($content, true) : false;
            if ($bytes === false || $bytes === '') {
                throw ValidationException::withMessages([
                    "attachments.{$i}.content" => 'Le contenu de la pièce jointe doit être un fichier encodé en base64.',
                ]);
            }

            $total += strlen($bytes);
            if ($total > self::MAX_TOTAL_BYTES) {
                throw ValidationException::withMessages([
                    'attachments' => 'Les pièces jointes dépassent la taille maximale autorisée (10 Mo au total).',
                ]);
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $decoded[] = [
                'name' => self::sanitizeFilename((string) ($attachment['name'] ?? '')),
                'bytes' => $bytes,
                'mime' => is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream',
            ];
        }

        return $decoded;
    }

    /**
     * @param  list<array{name: string, bytes: string, mime: string}>  $decoded
     * @return list<array{disk: string, path: string, name: string, mime: string, size: int}>
     */
    public function store(array $decoded, string $messageUuid): array
    {
        $stored = [];
        foreach ($decoded as $i => $file) {
            $path = self::DIRECTORY."/{$messageUuid}/".($i + 1).'-'.$file['name'];
            Storage::disk(self::DISK)->put($path, $file['bytes']);
            $stored[] = ['disk' => self::DISK, 'path' => $path, 'name' => $file['name'], 'mime' => $file['mime'], 'size' => strlen($file['bytes'])];
        }

        return $stored;
    }

    public function delete(string $messageUuid): void
    {
        Storage::disk(self::DISK)->deleteDirectory(self::DIRECTORY."/{$messageUuid}");
    }

    /** Base name only, no control / path / shell-special characters, at most 150 characters. */
    public static function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN._\- ()]+/u', '_', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '', ' ._-');

        if ($name === '') {
            return 'piece-jointe';
        }

        if (mb_strlen($name) > 150) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $extension = $extension !== '' && mb_strlen($extension) <= 10 ? '.'.$extension : '';
            $name = mb_substr($name, 0, 150 - mb_strlen($extension)).$extension;
        }

        return $name;
    }
}
