<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Swoole\Server;

class OptimizedHelloMicroservice
{
    private $server;

    // Pre-computed responses for better performance
    private $cachedResponses = [];

    public function __construct()
    {
        $this->server = new Server("127.0.0.1", 9003, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

        $this->precomputeResponses();
        $this->configureServer();
        $this->setupHandlers();
    }

    private function precomputeResponses()
    {
        // Pre-compute common responses to avoid JSON encoding on each request
        $this->cachedResponses['hello_get'] = json_encode([
            'status' => 200,
            'body' => json_encode([
                'success' => true,
                'message' => 'Hello World from PHP OpenSwoole Microservice!',
                'timestamp' => null, // Will be updated per request
                'service' => 'hello-microservice'
            ]),
            'content_type' => 'application/json'
        ]);
    }

    private function configureServer()
    {
        $this->server->set([
            'worker_num' => 2, // Reduced workers for microservice
            'daemonize' => false,
            'max_request' => 0, // No restart limit
            'dispatch_mode' => 2,
            'enable_coroutine' => true,
            'max_coroutine' => 1000,
            'log_level' => SWOOLE_LOG_WARNING, // Reduce logging

            // TCP optimizations
            'open_tcp_nodelay' => true, // Critical for low latency
            'tcp_fastopen' => true,
            'socket_buffer_size' => 64 * 1024, // 64KB buffer
            'package_max_length' => 64 * 1024, // Smaller max package for microservice

            // Connection optimizations
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => 120,
            'max_connection' => 1000,

            // Reduce overhead
            'log_file' => '/dev/null', // Disable file logging for performance
        ]);
    }

    private function setupHandlers()
    {
        $this->server->on('connect', function ($server, $fd) {
            // Minimal logging
        });

        $this->server->on('receive', function ($server, $fd, $reactorId, $data) {
            $this->handleRequestFast($server, $fd, $data);
        });

        $this->server->on('close', function ($server, $fd) {
            // Minimal logging
        });

        $this->server->on('start', function ($server) {
            echo "Optimized Hello Microservice started on 127.0.0.1:9003\n";
            echo "Master PID: {$server->master_pid}\n";
        });
    }

    private function handleRequestFast($server, $fd, $data)
    {
        try {
            // Fast JSON decode with error checking
            $request = json_decode($data, true);

            if (!$request || !isset($request['action'])) {
                $server->send($fd, '{"status":400,"body":"{\"error\":\"Invalid request\"}","content_type":"application/json"}');
                return;
            }

            $action = $request['action'];
            $method = $request['method'] ?? 'GET';

            // Direct action handling for maximum speed
            if ($action === 'hello') {
                if ($method === 'GET') {
                    // Use pre-computed response with updated timestamp
                    $response = json_decode($this->cachedResponses['hello_get'], true);
                    $body = json_decode($response['body'], true);
                    $body['timestamp'] = date('Y-m-d H:i:s');
                    $response['body'] = json_encode($body);

                    $server->send($fd, json_encode($response));
                } elseif ($method === 'POST') {
                    $name = $request['data']['name'] ?? 'World';

                    // Fast response generation
                    $response = [
                        'status' => 200,
                        'body' => json_encode([
                            'success' => true,
                            'message' => "Hello {$name}! Welcome to our PHP OpenSwoole microservice!",
                            'timestamp' => date('Y-m-d H:i:s'),
                            'service' => 'hello-microservice'
                        ]),
                        'content_type' => 'application/json'
                    ];

                    $server->send($fd, json_encode($response));
                } else {
                    $server->send($fd, '{"status":405,"body":"{\"error\":\"Method not allowed\"}","content_type":"application/json"}');
                }
            } else {
                $server->send($fd, '{"status":404,"body":"{\"error\":\"Action not found\"}","content_type":"application/json"}');
            }

        } catch (Exception $e) {
            $server->send($fd, '{"status":500,"body":"{\"error\":\"Server error\"}","content_type":"application/json"}');
        }
    }

    public function start()
    {
        $this->server->start();
    }
}

// Start the optimized Hello microservice
$helloService = new OptimizedHelloMicroservice();
$helloService->start();