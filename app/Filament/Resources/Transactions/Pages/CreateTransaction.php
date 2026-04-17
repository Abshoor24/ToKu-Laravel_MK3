<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

public function afterCreate()
{
    $total = 0;

    foreach ($this->record->items as $item) {
        $product = $item->product;

        // validasi stok
        if ($product->stock < $item->quantity) {
            throw new \Exception("Stock not enough for {$product->name}");
        }

        // hitung total
        $total += $item->price * $item->quantity;

        // kurangi stok
        $product->stock -= $item->quantity;
        $product->save();
    }

    // update total_price setelah semua item dibuat
    $this->record->update([
        'total_price' => $total
    ]);
}


}
