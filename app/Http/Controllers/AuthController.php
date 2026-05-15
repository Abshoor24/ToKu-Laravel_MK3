<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use App\Traits\ApiResponseTrait;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (substr($phone, 0, 1) == '0') {
            $phone = '62' . substr($phone, 1);
        }

        if (substr($phone, 0, 2) == '62') {
            return $phone;
        }

        return $phone;
    }

    public function register(Request $request)
    {
        $request->merge([
            'phone' => $this->formatPhone($request->phone),
        ]);

        $request->validate([
            'name'     => 'required',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone'    => [
                'required',
                'regex:/^(08|628|\+628)\d{8,13}$/',
                'unique:users,phone',
            ],
        ]);

        $otp = rand(100000, 999999);

        $user = User::create([
            'name'           => $request->name,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),
            'phone'          => $this->formatPhone($request->phone),
            'role'           => 'user',
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        Mail::to($user->email)->send(new OtpMail($otp));

        return $this->successResponse(
            null,
            'Register berhasil, cek email untuk OTP',
            201
        );
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse('User tidak ditemukan', 404);
        }

        if (!$user->otp || !$user->otp_expires_at) {
            return $this->errorResponse('OTP tidak valid / belum dibuat', 400);
        }

        if ((string)$user->otp !== (string)$request->otp) {
            return $this->errorResponse('OTP salah', 400);
        }

        if (now()->gt($user->otp_expires_at)) {
            return $this->errorResponse('OTP expired', 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'otp'               => null,
            'otp_expires_at'    => null,
        ]);

        return $this->successResponse(
            null,
            'Email berhasil diverifikasi'
        );
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->errorResponse('Email atau password salah', 401);
        }

        $user = Auth::user();

        if (!$user->email_verified_at) {
            return $this->errorResponse('Verifikasi email dulu', 403);
        }

        $token = $user->createToken('token')->plainTextToken;

        return $this->successResponse(
            [
                'token' => $token,
                'user'  => new \App\Http\Resources\UserResource($user),
            ],
            'Login berhasil'
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout berhasil');
    }
}