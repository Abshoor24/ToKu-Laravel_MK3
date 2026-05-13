```php
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

    # GET ALL
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

    # GET BY ID
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
            'items'               => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        try {

            $transaction = DB::transaction(function () use ($request, $user) {

                $total = 0;
                $itemsData = [];

                foreach ($request->items as $item) {

                    // Lock product row biar aman dari race condition
                    $product = Product::lockForUpdate()
                        ->findOrFail($item['product_id']);

                    // Validasi stock
                    if ($product->stock < $item['quantity']) {
                        throw new \Exception(
                            "Stock tidak cukup untuk produk {$product->name}"
                        );
                    }

                    $subtotal = $product->price * $item['quantity'];

                    $total += $subtotal;

                    $itemsData[] = [
                        'product' => $product,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                    ];

                    // Kurangi stock
                    $product->decrement('stock', $item['quantity']);
                }

                // Create transaction
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'total_price' => $total,
                ]);

                // Create transaction items
                foreach ($itemsData as $data) {

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $data['product']->id,
                        'quantity' => $data['quantity'],
                        'price' => $data['price'],
                    ]);
                }

                return $transaction;
            });

            $transaction->load('items.product', 'user');

            // Generate receipt
            $message = $this->generateReceipt($transaction);

            // Send WhatsApp receipt
            try {

                $this->waService->send(
                    $transaction->phone,
                    $message
                );

            } catch (\Throwable $e) {

                Log::warning(
                    'Failed send WA receipt transaction ID '
                    . $transaction->id .
                    ': ' .
                    $e->getMessage()
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil',
                'data' => new TransactionResource($transaction)
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    # DELETE
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

    # GENERATE RECEIPT
    public function generateReceipt($transaction): string
    {
        $message  = "🧾 *STRUK PEMBELIAN*\n\n";
        $message .= "Nama   : {$transaction->name}\n";
        $message .= "No HP  : {$transaction->phone}\n";
        $message .= "Tanggal: {$transaction->created_at->format('d/m/Y H:i')}\n\n";

        $message .= "📦 *Detail Produk:*\n";

        foreach ($transaction->items as $item) {

            $subtotal = number_format(
                $item->price * $item->quantity,
                0,
                ',',
                '.'
            );

            $message .= "- {$item->product->name} x{$item->quantity} = Rp{$subtotal}\n";
        }

        $message .= "\n💰 *Total: Rp" .
            number_format($transaction->total_price, 0, ',', '.') .
            "*\n";

        $message .= "\nTerima kasih telah berbelanja 🙏";

        return $message;
    }
}
```
