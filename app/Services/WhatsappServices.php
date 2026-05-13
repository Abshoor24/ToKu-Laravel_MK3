<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class WhatsAppServices {
    public function send($phone, $message) {
        $token = Config::get('services.whatsapp.token');

        if (empty($token)) {
            throw new \RuntimeException('WhatsApp token is not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->post('https://api.fonnte.com/send', [
            'target' => $phone,
            'message' => $message,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('WhatsApp API request failed.');
        }
    }
}