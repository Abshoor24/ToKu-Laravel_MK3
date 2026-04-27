<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\WhatsAppServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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
        $transactions = Transaction::with('items.product', 'user')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($transactions)
        ]);
    }

    # READ BY ID
    public function show($id)
    {
        $transaction = Transaction::with('items.product', 'user')->find($id);

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
        #validasi field
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        # mulai transaksi
        DB::beginTransaction();

        try {
            $total = 0;

            $products = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stock not enough for {$product->name}");
                }

                $products[] = $product;

                $total += $product->price * $item['quantity'];
            }
            #buat transaksi
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'name' => $user->name,
                'phone' => $user->phone,
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

            $transaction->load('items.product');

            // generate receipt
            $message = $this->generateReceipt($transaction);

            // kirim WA
            $this->waService->send($transaction->phone, $message);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil',
                'data' => new TransactionResource($transaction->load('items.product', 'user'))
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
