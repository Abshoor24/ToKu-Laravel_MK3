<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'user'        => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'name'        => $this->name,
            'phone'       => $this->phone,
            'total_price' => (float) $this->total_price,
            'created_at'  => $this->created_at?->toIso8601String(),
            'items'       => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'product'  => [
                        'id'    => $item->product->id,
                        'name'  => $item->product->name,
                        'price' => (float) $item->product->price,
                    ],
                    'quantity' => $item->quantity,
                    'price'    => (float) $item->price,
                    'subtotal' => (float) ($item->price * $item->quantity),
                ])
            ),
        ];
    }
}