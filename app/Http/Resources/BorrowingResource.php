<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BorrowingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'borrowed_at' => $this->borrowed_at?->format('Y-m-d H:i:s'),
            'due_at' => $this->due_at?->format('Y-m-d H:i:s'),
            'returned_at' => $this->returned_at?->format('Y-m-d H:i:s'),
            'is_returned' => $this->isReturned(),
            'is_overdue' => $this->isOverdue(),
            'days_until_due' => $this->due_at ? now()->diffInDays($this->due_at, false) : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Include relationships if loaded
            'user' => new UserResource($this->whenLoaded('user')),
            'book' => new BookResource($this->whenLoaded('book')),
        ];
    }
}