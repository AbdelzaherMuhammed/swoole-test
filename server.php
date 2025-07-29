<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Coroutine\Client;

class OptimizedMainServer
{
    private $server;
    private $microservices = [
        'hello' => 'tcp://127.0.0.1:9003',
    ];

    // Connection pool for reusing TCP connections
    private $connectionPool = [];
    private $maxPoolSize = 10;

    public function __construct()
    {
        $this->server = new Server("0.0.0.0", 80);
        $this->server->addListener("0.0.0.0", 443, SWOOLE_SOCK_TCP | SWOOLE_SSL);

        $this->configureServer();
        $this->setupRoutes();
        $this->initConnectionPool();
    }

    private function configureServer()
    {
        $this->server->set([
            'worker_num' => OpenSwoole\Util::getCPUNum() * 2,
            'daemonize' => false,
            'max_request' => 0, // No restart limit for better performance
            'dispatch_mode' => 2,
            'debug_mode' => 0, // Disable debug in production
            'enable_coroutine' => true,
            'max_coroutine' => 3000, // Increase coroutine limit
            'log_file' => __DIR__ . '/logs/server.log',
            'log_level' => 1, // 1 = WARNING level in OpenSwoole

            // TCP optimizations
            'open_tcp_nodelay' => true, // Disable Nagle algorithm for lower latency
            'tcp_fastopen' => true, // Enable TCP Fast Open
            'socket_buffer_size' => 2 * 1024 * 1024, // 2MB buffer

            // HTTP optimizations
            'package_max_length' => 8 * 1024 * 1024, // 8MB max package
            'buffer_output_size' => 32 * 1024, // 32KB output buffer

            // Connection optimizations
            'heartbeat_check_interval' => 30,
            'heartbeat_idle_time' => 60,

            // SSL Configuration
            'ssl_cert_file' => __DIR__ . '/ssl/server.crt',
            'ssl_key_file' => __DIR__ . '/ssl/server.key',
        ]);
    }

    private function initConnectionPool()
    {
        // Pre-warm connection pools for each microservice
        foreach ($this->microservices as $service => $address) {
            $this->connectionPool[$service] = new \SplQueue();
        }
    }

    private function setupRoutes()
    {
        $this->server->on('request', function (Request $request, Response $response) {
            $this->handleRequest($request, $response);
        });

        $this->server->on('start', function ($server) {
            echo "Optimized Server started on HTTP:80 and HTTPS:443\n";
            echo "Master PID: {$server->master_pid}\n";
        });

        $this->server->on('workerStart', function ($server, $workerId) {
            // Pre-create some connections in each worker
            $this->preWarmConnections();
            echo "Worker #{$workerId} started with pre-warmed connections\n";
        });
    }

    private function preWarmConnections()
    {
        foreach ($this->microservices as $service => $address) {
            for ($i = 0; $i < 3; $i++) { // Pre-create 3 connections per service
                $client = $this->createConnection($service);
                if ($client && $client->isConnected()) {
                    if ($this->connectionPool[$service]->count() < $this->maxPoolSize) {
                        $this->connectionPool[$service]->enqueue($client);
                    }
                }
            }
        }
    }

    private function createConnection($service)
    {
        if (!isset($this->microservices[$service])) {
            return null;
        }

        $url = parse_url($this->microservices[$service]);
        $client = new OpenSwoole\Coroutine\Client(OpenSwoole\Constant::SOCK_TCP);

        // Optimize client settings
        $client->set([
            'timeout' => 0.5, // 500ms timeout instead of 1s
            'connect_timeout' => 0.2, // 200ms connect timeout
            'write_timeout' => 0.3,
            'read_timeout' => 0.3,
            'open_tcp_nodelay' => true, // Disable Nagle algorithm
            'socket_buffer_size' => 64 * 1024, // 64KB buffer
        ]);

        if ($client->connect($url['host'], $url['port'], 0.2)) {
            return $client;
        }

        return null;
    }

    private function getConnection($service)
    {
        // Try to get from pool first
        if (!$this->connectionPool[$service]->isEmpty()) {
            $client = $this->connectionPool[$service]->dequeue();
            if ($client && $client->isConnected()) {
                return $client;
            }
        }

        // Create new connection if pool is empty
        return $this->createConnection($service);
    }

    private function returnConnection($service, $client)
    {
        // Return connection to pool if it's still valid and pool isn't full
        if ($client && $client->isConnected() && $this->connectionPool[$service]->count() < $this->maxPoolSize) {
            $this->connectionPool[$service]->enqueue($client);
        } else if ($client) {
            $client->close();
        }
    }

    private function handleRequest(Request $request, Response $response)
    {
        $path = $request->server['request_uri'];
        $method = $request->server['request_method'];

        // Minimal CORS headers
        $response->header('Access-Control-Allow-Origin', '*');

        if ($method === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return;
        }

        // Direct routing without switch for better performance
        if ($path === '/') {
            $this->handleHome($response);
        } elseif ($path === '/hello') {
            $this->fastProxyToMicroservice('hello', 'hello', $request, $response);
        } else {
            $response->status(404);
            $response->end('Not Found');
        }
    }

    private function handleHome(Response $response)
    {
        // Simplified HTML for faster response
        $html = '<html><head><title>Optimized Server</title></head><body><h1>Server Running</h1><a href="/hello">Hello</a></body></html>';
        $response->header('Content-Type', 'text/html');
        $response->end($html);
    }

    private function fastProxyToMicroservice(string $service, string $action, Request $request, Response $response)
    {
        go(function() use ($service, $action, $request, $response) {
            $startTime = microtime(true);

            try {
                $client = $this->getConnection($service);

                if (!$client) {
                    $response->status(503);
                    $response->end('Service Unavailable');
                    return;
                }

                // Prepare minimal request data
                $requestData = [
                    'action' => $action,
                    'method' => $request->server['request_method']
                ];

                // Only add data if it exists to reduce payload size
                if (!empty($request->post)) {
                    $requestData['data'] = $request->post;
                } elseif (!empty($request->get)) {
                    $requestData['data'] = $request->get;
                }

                $payload = json_encode($requestData);

                // Send with error checking
                if (!$client->send($payload)) {
                    $this->returnConnection($service, null); // Don't return bad connection
                    $response->status(500);
                    $response->end('Send Failed');
                    return;
                }

                $result = $client->recv(0.5); // 500ms timeout for receive

                if ($result === false || $result === '') {
                    $this->returnConnection($service, null); // Don't return bad connection
                    $response->status(500);
                    $response->end('Receive Failed');
                    return;
                }

                // Return connection to pool
                $this->returnConnection($service, $client);

                $serviceResponse = json_decode($result, true);

                if (!$serviceResponse) {
                    $response->status(500);
                    $response->end('Invalid Response');
                    return;
                }

                // Add timing header for debugging
                $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                $response->header('X-Processing-Time', $processingTime . 'ms');

                $response->status($serviceResponse['status'] ?? 200);
                $response->header('Content-Type', $serviceResponse['content_type'] ?? 'application/json');
                $response->end($serviceResponse['body'] ?? '');

            } catch (Exception $e) {
                $response->status(500);
                $response->end('Error: ' . $e->getMessage());
            }
        });
    }

    public function start()
    {
        $this->server->start();
    }
}

// Start the optimized server
$server = new OptimizedMainServer();
$server->start();