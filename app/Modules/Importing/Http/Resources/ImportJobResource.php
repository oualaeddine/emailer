<?php

namespace App\Modules\Importing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin \App\Modules\Importing\Models\ImportJob
 */
class ImportJobResource extends JsonResource
{
    /** @var array<string, mixed>|null */
    private ?array $preview = null;

    /**
     * @param  array<string, mixed>  $preview  §45.5.7 ImportPreview
     */
    public function withPreview(array $preview): static
    {
        $this->preview = $preview;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'source_type' => $this->source_type,
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'imported_count' => $this->imported_count,
            'duplicate_count' => $this->duplicate_count,
            'error_count' => $this->error_count,
            'column_mapping' => $this->column_mapping,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // docs/45-brevo-api-contracts.md §45.5.7 — ImportJobV2.
            'list_ids' => $this->list_ids ?? [],
            'new_list' => $this->new_list,
            'mapping' => $this->mapping ?? [],
            'update_existing' => (bool) $this->update_existing,
            'empty_values_overwrite' => (bool) $this->empty_values_overwrite,
            'mark_blocklisted' => (bool) $this->mark_blocklisted,
            'mark_opt_in' => (bool) $this->mark_opt_in,
            'summary' => $this->summary,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->uuid,
                'name' => $this->user->name,
            ], null),
            'preview' => $this->preview,
            'errors_url' => $this->hasErrors()
                ? URL::temporarySignedRoute('import-jobs.errors', now()->addHour(), ['uuid' => $this->uuid], false)
                : null,
        ];
    }

    private function hasErrors(): bool
    {
        $counted = $this->resource->getAttribute('errors_count');

        return $counted !== null ? $counted > 0 : ($this->error_count > 0 || $this->errors()->exists());
    }
}
