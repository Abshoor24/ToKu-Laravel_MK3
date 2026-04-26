<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappService {
    public function send($phone, $message) {
        $token = env('TOKEN_FONTE');

        Http::withHeaders([
            'Authorization' => $token,
        ])->post('https://api.fonnte.com/send', [
            'target' => $phone,
            'message' => $message,
        ]);
    }
}