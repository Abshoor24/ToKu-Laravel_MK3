<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ProductController;
use App\Http\Resources\UserResource;
// use App\Traits\ApiResponseTrait;

// Public — throttle dilonggarkan untuk development
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/register',   [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/login',      [AuthController::class, 'login']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    
    Route::get('/products',       [ProductController::class, 'index']);
    Route::get('/products/{id}',  [ProductController::class, 'show']);
    Route::get('/categories',     [ProductController::class, 'categories']);
    Route::get('/brands',         [ProductController::class, 'brand']);
});

// Protected
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/transactions',       [TransactionController::class, 'index']);
    Route::get('/transactions/{id}',  [TransactionController::class, 'show']);
    Route::post('/transactions',      [TransactionController::class, 'store']);

    Route::post('/logout', [AuthController::class, 'logout']);

    // FIX: /me sekarang pakai wrapper {success, message, data}
    Route::get('/me', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diambil',
            'data'    => new UserResource($request->user()),
        ]);
    });
});