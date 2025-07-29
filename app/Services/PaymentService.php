<?php

namespace App\Services;

class PaymentService {
    public static function handle($payload): string {
        if (is_string($payload)) {
            $amount = unpack('J', substr($payload, 0, 8))[1];
            return 'PAID:' . $amount;
        }
        return [
            'transaction_id' => bin2hex(random_bytes(8)),
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'USD'
        ];
    }
}
