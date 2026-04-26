<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppService;

class TransactionController extends Controller
{   
    protected $waService;

    public function __construct(WhatsAppService $waService)
    {
        $this->waService = $waService;
    }
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
            'name' => 'required|string',
            'phone' => 'required|string',
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
                'user_id' => auth()->id(),
                'name' => $request->name,
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

            $transaction->load('items.product');

            // generate receipt
            $message = $this->generateReceipt($transaction);

            // kirim WA
            $this->waService->send($transaction->phone, $message);

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
