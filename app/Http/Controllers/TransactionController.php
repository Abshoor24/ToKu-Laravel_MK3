<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\WhatsAppServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\TransactionResource;

class TransactionController extends Controller
{
    protected $waService;

    public function __construct(WhatsAppServices $waService)
    {
        $this->waService = $waService;
    }
    # GET
    public function index()
    {
        $transactions = Transaction::with('items.product', 'user')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($transactions)
        ]);
    }

    # READ BY ID
    public function show($id)
    {
        $transaction = Transaction::with('items.product', 'user')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new TransactionResource($transaction)
        ]);
    }

    # CREATE
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            $productIds = collect($request->items)->pluck('product_id')->unique();

            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $itemsByProduct = collect($request->items)
                ->groupBy('product_id')
                ->map(fn ($items) => $items->sum('quantity'));

            $total = 0;

            foreach ($itemsByProduct as $productId => $quantity) {
                $product = $products->get($productId);

                if (!$product) {
                    throw new \Exception("Product ID {$productId} tidak ditemukan.");
                }

                if ($product->stock < $quantity) {
                    throw new \Exception("Stock not enough for {$product->name}");
                }

                $total += $product->price * $quantity;
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'total_price' => $total,
            ]);

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);
            }

            DB::commit();

            $transaction->load('items.product', 'user');

            $message = $this->generateReceipt($transaction);

            try {
                $this->waService->send($transaction->phone, $message);
            } catch (\Throwable $e) {
                Log::warning('Failed to send WhatsApp receipt for transaction '.$transaction->id.': '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil',
                'data' => new TransactionResource($transaction)
            ]);
        } catch (\Throwable $e) {
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
        $transaction = Transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted'
        ]);
    }

    public function generateReceipt($transaction)
    {
        $message = "🧾 *STRUK PEMBELIAN*\n\n";
        $message .= "Nama: {$transaction->name}\n";
        $message .= "No HP: {$transaction->phone}\n";
        $message .= "Tanggal: {$transaction->created_at}\n\n";

        $message .= "📦 *Detail Produk:*\n";

        foreach ($transaction->items as $item) {
            $message .= "- {$item->product->name} x{$item->quantity} = Rp" . number_format($item->price * $item->quantity) . "\n";
        }

        $message .= "\n💰 *Total: Rp" . number_format($transaction->total_price) . "*\n";
        $message .= "\nTerima kasih 🙏";

        return $message;
    }
}