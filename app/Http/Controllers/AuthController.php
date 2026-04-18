<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
// use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'required'
        ]);

        $otp = rand(100000, 999999);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        Mail::raw("Kode OTP kamu: $otp", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Kode OTP');
        });

        return response()->json([
            'message' => 'Register berhasil, cek email untuk OTP'
        ]);
    }

public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'User tidak ditemukan'], 404);
    }

    if (!$user->otp || !$user->otp_expires_at) {
        return response()->json(['message' => 'OTP tidak valid / belum dibuat'], 400);
    }

    if ((string)$user->otp !== (string)$request->otp) {
        return response()->json(['message' => 'OTP salah'], 400);
    }

    if (now()->gt($user->otp_expires_at)) {
        return response()->json(['message' => 'OTP expires'], 400);
    }

    $user->update([
        'email_verified_at' => now(),
        'otp' => null,
        'otp_expires_at' => null
    ]);

    return response()->json([
        'message' => 'Email berhasil diverifikasi'
    ]);
}

public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Email atau password salah'], 401);
    }

    $user = Auth::user();

    if (!$user->email_verified_at) {
        return response()->json(['message' => 'Verifikasi email dulu'], 403);
    }

    return response()->json([
        'message' => 'Login berhasil',
        'user' => $user
    ]);
}
}
