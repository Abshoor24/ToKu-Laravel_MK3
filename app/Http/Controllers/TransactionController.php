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
            'items'                 => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $user) {
                $total = 0;
                $itemsData = [];

                foreach ($request->items as $item) {
                    // FIX: lockForUpdate() agar tidak race condition
                    // Semua produk dikunci dan diproses dalam 1 loop
                    $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Stok tidak cukup untuk produk: {$product->name}. Stok tersedia: {$product->stock}");
                    }

                    $subtotal = $product->price * $item['quantity'];
                    $total += $subtotal;

                    // Simpan data sementara untuk insert TransactionItem
                    $itemsData[] = [
                        'product'  => $product,
                        'quantity' => $item['quantity'],
                        'price'    => $product->price,
                    ];

                    // Langsung decrement dalam 1 loop (tidak terpisah) mengurangi stock item bersadarkan quantity yang dibeli
                    $product->decrement('stock', $item['quantity']);
                }

                // Buat transaksi
                $transaction = Transaction::create([
                    'user_id'     => $user->id,
                    'name'        => $user->name,
                    'phone'       => $user->phone,
                    'total_price' => $total,
                ]);

                // Buat transaction items
                foreach ($itemsData as $data) {
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id'     => $data['product']->id,
                        'quantity'       => $data['quantity'],
                        'price'          => $data['price'],
                    ]);
                }

                return $transaction;
            });

            $result->load('items.product', 'user');

            // Generate & kirim receipt WhatsApp
            $message = $this->generateReceipt($result);
            $this->waService->send($result->phone, $message);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil',
                'data'    => new TransactionResource($result)
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
        // FIX: syntax where() yang benar
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

    # GENERATE RECEIPT WA
    public function generateReceipt($transaction): string
    {
        $message  = "🧾 *STRUK PEMBELIAN*\n\n";
        $message .= "Nama   : {$transaction->name}\n";
        $message .= "No HP  : {$transaction->phone}\n";
        $message .= "Tanggal: {$transaction->created_at->format('d/m/Y H:i')}\n\n";
        $message .= "📦 *Detail Produk:*\n";

        foreach ($transaction->items as $item) {
            $subtotal = number_format($item->price * $item->quantity, 0, ',', '.');
            $message .= "- {$item->product->name} x{$item->quantity} = Rp{$subtotal}\n";
        }

        $message .= "\n💰 *Total: Rp" . number_format($transaction->total_price, 0, ',', '.') . "*\n";
        $message .= "\nTerima kasih telah berbelanja 🙏";

        return $message;
    }
}