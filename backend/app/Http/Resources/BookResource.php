<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
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
            'isbn' => $this->isbn,
            'edition' => $this->edition,
            'publish_year' => $this->publish_year,
            'language' => $this->language,
            'pages' => $this->pages,
            'description' => $this->description,
            'cover_url' => $this->cover_url,
            'total_copies' => $this->total_copies,
            'available_copies' => $this->available_copies,
            'price' => $this->price,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
            ]),
            'shelf' => $this->whenLoaded('shelf', fn() => [
                'id' => $this->shelf->id,
                'name' => $this->shelf->name,
                'code' => $this->shelf->code,
                'location' => $this->shelf->location,
            ]),
            'publisher' => $this->whenLoaded('publisher', fn() => [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
            ]),
            'authors' => $this->whenLoaded('authors', fn() => $this->authors->map(fn($author) => [
                'id' => $author->id,
                'name' => $author->name,
                'is_primary' => $author->pivot->is_primary,
            ])),
            'copies_count' => $this->whenCounted('copies'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
