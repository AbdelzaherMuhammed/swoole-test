<?php

namespace App\Services;

class AuthService {
    public static function handle($payload): string {
        // Binary protocol gets raw payload
        if (is_string($payload)) {
            return 'AUTH:' . bin2hex(substr($payload, 0, 8));
        }
        // JSON-RPC gets array
        return [
            'user_id' => $payload['user_id'] ?? null,
            'auth_time' => microtime(true),
            'status' => 'authenticated'
        ];
    }
}
