<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

class UltraOptimizedMainServer
{
    private $server;

    // Pre-computed static responses for maximum speed
    private static $staticResponses = [];
    private static $currentTimestamp;

    public function __construct()
    {
        $this->server = new Server("0.0.0.0", 80);
        $this->precomputeStaticResponses();
        $this->configureServer();
        $this->setupRoutes();
    }

    private function precomputeStaticResponses()
    {
        // Pre-compute all possible responses to avoid runtime JSON encoding
        self::$staticResponses = [
            'hello_get' => json_encode([
                'success' => true,
                'message' => 'Hello World from PHP OpenSwoole Microservice!',
                'service' => 'hello-microservice'
            ]),
            'home' => '<!DOCTYPE html><html><head><title>Optimized Server</title></head><body><h1>Server Running</h1><a href="/hello">Hello</a></body></html>',
            'not_found' => '{"error":"Not Found"}',
            'method_not_allowed' => '{"error":"Method not allowed"}'
        ];
    }

    private function configureServer()
    {
        $this->server->set([
            'worker_num' => OpenSwoole\Util::getCPUNum() * 2,
            'daemonize' => false,
            'max_request' => 0,
            'dispatch_mode' => 2,
            'enable_coroutine' => true,
            'max_coroutine' => 3000, // Increase coroutine limit
            'log_file' => __DIR__ . '/logs/server.log',
            'log_level' => 1, // 1 = WARNING level in OpenSwoole

            // Aggressive TCP optimizations for cloud
            'open_tcp_nodelay' => true,
            'tcp_fastopen' => true,
            'socket_buffer_size' => 2 * 1024 * 1024,
            'buffer_output_size' => 128 * 1024, // Larger buffer for cloud

            // HTTP optimizations
            'package_max_length' => 2 * 1024 * 1024,
            'http_compression' => false, // Disable compression for speed
            'http_gzip_level' => 0,

            // Connection optimizations
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => 120,
            'max_connection' => 2000,

            // Disable unnecessary features
            'enable_static_handler' => false,
        ]);
    }

    private function setupRoutes()
    {
        $this->server->on('request', function (Request $request, Response $response) {
            $this->handleRequestUltraFast($request, $response);
        });

        $this->server->on('start', function ($server) {
            echo "Ultra-Optimized Server started on port 80\n";
            echo "Master PID: {$server->master_pid}\n";
        });

        $this->server->on('workerStart', function ($server, $workerId) {
            // Update timestamp every second in each worker
            swoole_timer_tick(1000, function() {
                self::$currentTimestamp = date('Y-m-d H:i:s');
            });
            self::$currentTimestamp = date('Y-m-d H:i:s');
            echo "Worker #{$workerId} started\n";
        });
    }

    private function handleRequestUltraFast(Request $request, Response $response)
    {
        $path = $request->server['request_uri'];
        $method = $request->server['request_method'];

        // Minimal headers
        $response->header('Content-Type', 'application/json');
        $response->header('Connection', 'keep-alive');

        // Handle OPTIONS quickly
        if ($method === 'OPTIONS') {
            $response->status(200);
            $response->end('');
            return;
        }

        // Ultra-fast routing without function calls
        if ($path === '/') {
            $response->header('Content-Type', 'text/html');
            $response->end(self::$staticResponses['home']);
        } elseif ($path === '/hello') {
            if ($method === 'GET') {
                // Inline response generation for maximum speed
                $responseBody = '{"success":true,"message":"Hello World from PHP OpenSwoole Microservice!","timestamp":"' . self::$currentTimestamp . '","service":"hello-microservice"}';
                $response->end($responseBody);
            } elseif ($method === 'POST') {
                $name = $request->post['name'] ?? 'World';
                $responseBody = '{"success":true,"message":"Hello ' . htmlspecialchars($name, ENT_QUOTES) . '! Welcome to our PHP OpenSwoole microservice!","timestamp":"' . self::$currentTimestamp . '","service":"hello-microservice"}';
                $response->end($responseBody);
            } else {
                $response->status(405);
                $response->end(self::$staticResponses['method_not_allowed']);
            }
        } else {
            $response->status(404);
            $response->end(self::$staticResponses['not_found']);
        }
    }

    public function start()
    {
        $this->server->start();
    }
}

// Start the ultra-optimized server
$server = new UltraOptimizedMainServer();
$server->start();