<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Resources\UserResource;
use App\Http\Controllers\ProductController;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\User;
use App\Models\Transaction;

// Public
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
});

// Protected
Route::middleware('auth:sanctum')->group(function () {


    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    // Route::delete('/transactions/{id}', [TransactionController::class, 'delete']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return new UserResource($request->user());
    });
});

// CEK RESPONSE 
Route::get('/debug-all', function () {

    return response()->json([

        // Collection
        'products' => ProductResource::collection(Product::all()),

        'transactions' => TransactionResource::collection(Transaction::all()),

        // Single item
        'product_detail' => new ProductResource(Product::first()),

        'transaction_detail' => new TransactionResource(Transaction::first()),

        // User
        'user' => new UserResource(User::first()),

        // Simulasi login response
        'login_response' => [
            'message' => 'Login success',
            'token' => 'example_token_123',
            'user' => new UserResource(User::first()),
        ],

        // Simulasi register response
        'register_response' => [
            'message' => 'Register success',
            'user' => new UserResource(User::first()),
        ],

        // Simulasi logout
        'logout_response' => [
            'message' => 'Logout success'
        ],

    ]);

});