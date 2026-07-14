<?php

namespace App\Modules\Importing\Services;

use App\Domain\Enums\ImportJobStatus;
use App\Domain\Enums\ImportSourceType;
use App\Modules\Identity\Models\User;
use App\Modules\Importing\Models\ImportJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * docs/14-import-export.md §14.2 — Pipeline Stages: Upload/Select.
 */
class ImportJobService
{
    private const DISK = 'local';

    public function createFromUpload(UploadedFile $file, ImportSourceType $sourceType, User $user): ImportJob
    {
        $path = $file->store('imports', self::DISK);

        return ImportJob::query()->create([
            'user_id' => $user->id,
            'source_type' => $sourceType->value,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'status' => ImportJobStatus::Pending->value,
        ]);
    }

    public function absolutePath(ImportJob $job): string
    {
        return Storage::disk(self::DISK)->path($job->file_path);
    }
}
