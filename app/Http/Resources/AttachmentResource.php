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
            'attachable_type' => $this->__get('attachable_type'),
            'attachable_id' => $this->__get('attachable_id'),
            'collection' => $this->__get('collection'),
            'file_name' => $this->__get('file_name'),
            'mime_type' => $this->__get('mime_type'),
            'size' => $this->__get('size'),
            'path' => $this->__get('path'),
        ];
    }
}
