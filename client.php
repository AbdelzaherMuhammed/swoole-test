<?php
// client.php

use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Client;

require __DIR__ . '/vendor/autoload.php';

Coroutine::run(function () {
    // Binary Protocol Test
    $client = new Client(SWOOLE_SOCK_TCP);
    if (!$client->connect('127.0.0.1', 9000, 0.5)) {
        echo "Binary Protocol: connection failed\n";
        return;
    }
    $binaryRequest = pack('CNn', 0x01, 0x1001, 1) . 'auth_data';
    $client->send($binaryRequest);
    echo "Binary Response: " . $client->recv() . PHP_EOL;
    $client->close();

    // JSON-RPC Test
    $client = new Client(SWOOLE_SOCK_TCP);
    if (!$client->connect('127.0.0.1', 9000, 0.5)) {
        echo "JSON-RPC: connection failed\n";
        return;
    }
    $jsonRequest = json_encode([
        'service' => 'payment',
        'data' => ['amount' => 100, 'currency' => 'USD'],
        'time' => microtime(true)
    ]);
    $client->send($jsonRequest);
    echo "JSON Response: " . $client->recv() . PHP_EOL;
    $client->close();

    // HTTP Test
    $client = new Client(SWOOLE_SOCK_TCP);
    if (!$client->connect('127.0.0.1', 9000, 0.5)) {
        echo "HTTP: connection failed\n";
        return;
    }
    $client->send("GET / HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
    echo "HTTP Response: " . $client->recv() . PHP_EOL;
    $client->close();
});
