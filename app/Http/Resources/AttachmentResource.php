<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->__get('id'),
            'name' => $this->__get('original_name'),
            'collection' => $this->__get('collection'),
            'mime_type' => $this->__get('mime_type'),
            'size' => $this->__get('size'),
        ];
    }
}
