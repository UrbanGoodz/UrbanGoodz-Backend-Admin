<?php

namespace Modules\ReelsModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReelCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $removed = $this->status !== 'published';

        return [
            'id' => $this->id,
            'reel_id' => $this->reel_id,
            'parent_id' => $this->parent_id,
            'body' => $removed ? null : $this->body,
            'status' => $this->status,
            'author' => $removed ? null : [
                'id' => $this->user?->id,
                'name' => trim((string) ($this->user?->f_name.' '.$this->user?->l_name)),
                'image_url' => $this->user?->image_full_url,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'replies' => ReelCommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
