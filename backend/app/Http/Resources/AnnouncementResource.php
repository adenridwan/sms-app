<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'image_url' => $this->image ? asset('storage/' . $this->image) : null,
            'attachments' => $this->attachments,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'target_audience' => $this->target_audience,
            'publish_at' => $this->publish_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_pinned' => $this->is_pinned,
            'is_published' => $this->is_published,
            'is_active' => $this->is_active,
            'send_notification' => $this->send_notification,
            'author' => $this->whenLoaded('author', fn() => [
                'id' => $this->author->id,
                'email' => $this->author->email,
                'full_name' => $this->author->full_name,
            ]),
            'read_count' => $this->read_count,
            'is_read' => $this->is_read ?? false,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
