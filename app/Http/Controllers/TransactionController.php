<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller 
{   
    # GET
    public function index() {
        $transaction = Transaction::with('items.product')->latest()->get();

        return response()->json([
            'seccess' => true,
            'data' => $transaction
        ]);
    }

    # READ BY ID
    public function show($id) {
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
    public function store(Request $request) {
        $request->validate([
             'customer_name' => 'required|string',
            'phone' => 'required|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
    }

    public function delete($id) {
        $transaction = Transaction::find($id);
    }
}