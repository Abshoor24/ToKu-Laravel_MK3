<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'category' => $this->category,
            'stock' => $this->stock,
            'description' => $this->description,
            'image' => $this->image ? asset('storage/' . $this->image) : null, # mengubahnama file yang tersimpan di database menjadi URL lengkap yang bisa diakses browser/frontend.
            'created_at' => $this->created_at
        ];
    }
}