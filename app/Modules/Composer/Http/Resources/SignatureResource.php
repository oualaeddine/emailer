<?php

namespace App\Modules\Composer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Composer\Models\Signature
 */
class SignatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'html_content' => $this->html_content,
            'is_default' => $this->is_default,
        ];
    }
}
