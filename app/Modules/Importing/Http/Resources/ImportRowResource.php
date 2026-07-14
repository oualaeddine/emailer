<?php

namespace App\Modules\Importing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Importing\Models\ImportRow
 */
class ImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'raw_data' => $this->raw_data,
            'parsed_email' => $this->parsed_email,
            'validation_status' => $this->validation_status,
        ];
    }
}
