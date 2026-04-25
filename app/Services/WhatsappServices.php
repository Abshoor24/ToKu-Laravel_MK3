<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappService {
    public function send($phone, $message) {
        $token = env('TOKEN_FONTE');

        Http::withHeader([
            'Authorization' => $token,
        ])->post('https://api.fonnte.com/send', [
            'target' => $phone,
            'message' => $message,
        ]);
    }
}