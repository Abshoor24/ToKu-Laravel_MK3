<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    # GET
    public function index()
    {
        $transaction = Transaction::with('items.product')->latest()->get();

        return response()->json([
            'seccess' => true,
            'data' => $transaction
        ]);
    }

    # READ BY ID
    public function show($id)
    {
        $transaction = Transaction::with('items.product')->find($id);

        if (!$transaction) {
            return response()->json([
                'seccess' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'seccess' => true,
            'data' => $transaction
        ]);
    }

    # CREATE
    public function store(Request $request)
    {
        #validasi field
        $request->validate([
            'customer_name' => 'required|string',
            'phone' => 'required|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        # mulai transaksi
        DB::beginTransaction();

        try {
            $total = 0;

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                #validasi stok
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stock not enough for {$product->name}");
                }

                #hitung total
                $total = $product->price * $item['quantity'];
            }

            #buat transaksi
            $transaction = Transaction::create([
                'customer_name' => $request->customer_name,
                'phone' => $request->phone,
                'total_price' => $total,
            ]);

            #buat transaksi item
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil',
                'data' => $transaction->load('items.product')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #delete
    public function delete($id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json([
                'seccess' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        $transaction->delete();

        return response()->json([
            'seccess' => true,
            'message' => 'Transaction deleted'
        ]);
    }
}
