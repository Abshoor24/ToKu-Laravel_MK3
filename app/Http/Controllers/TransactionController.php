<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

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
    public function store($id) {
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
    
}