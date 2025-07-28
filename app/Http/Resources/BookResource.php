<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'genre' => $this->genre,
            'isbn' => $this->isbn,
            'total_copies' => $this->total_copies,
            'available_copies' => $this->available_copies,
            'is_available' => $this->isAvailable(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Include borrowings relationship if loaded
            'borrowings' => BorrowingResource::collection($this->whenLoaded('borrowings')),
        ];
    }
}