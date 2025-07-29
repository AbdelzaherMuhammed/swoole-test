<?php
require_once __DIR__ . '/vendor/autoload.php';

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

class MainServer
{
    private $server;
    private $microservices = [
        'hello' => 'tcp://127.0.0.1:9003',
    ];

    public function __construct()
    {
        // Create HTTP server on port 80 and HTTPS on 443
        $this->server = new Server("0.0.0.0", 80);

        // Add HTTPS listener
        $this->server->addListener("0.0.0.0", 443, SWOOLE_SOCK_TCP | SWOOLE_SSL);

        $this->configureServer();
        $this->setupRoutes();
    }

    private function configureServer()
    {
        $this->server->set([
            'worker_num' => OpenSwoole\Util::getCPUNum() * 2,
            'daemonize' => false,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'debug_mode' => 1,
            'enable_coroutine' => true,
            'log_file' => __DIR__ . '/logs/server.log',

            // HTTPS SSL Configuration
            'ssl_cert_file' => __DIR__ . '/ssl/server.crt',
            'ssl_key_file' => __DIR__ . '/ssl/server.key',
        ]);
    }

    private function setupRoutes()
    {
        $this->server->on('request', function (Request $request, Response $response) {
            $this->handleRequest($request, $response);
        });

        $this->server->on('start', function ($server) {
            echo "Main Server started on HTTP:80 and HTTPS:443\n";
            echo "Master PID: {$server->master_pid}\n";
        });

        $this->server->on('workerStart', function ($server, $workerId) {
            echo "Worker #{$workerId} started\n";
        });
    }

    private function handleRequest(Request $request, Response $response)
    {
        $path = $request->server['request_uri'];
        $method = $request->server['request_method'];

        // Add CORS headers
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        if ($method === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return;
        }

        // Route handling
        switch ($path) {
            case '/':
                $this->handleHome($response);
                break;
            case '/hello':
                $this->proxyToMicroservice('hello', 'hello', $request, $response);
                break;
            default:
                $response->status(404);
                $response->end('Route not found');
        }
    }

    private function handleHome(Response $response)
    {
        $html = '
        <html>
        <head><title>PHP OpenSwoole Server</title></head>
        <body>
            <h1>Main Server Running</h1>
            <p>Available endpoints:</p>
            <ul>
                <li><a href="/hello">/hello</a></li>
            </ul>
        </body>
        </html>';

        $response->header('Content-Type', 'text/html');
        $response->end($html);
    }

    private function proxyToMicroservice(string $service, string $action, Request $request, Response $response)
    {
        if (!isset($this->microservices[$service])) {
            $response->status(404);
            $response->end('Service not found');
            return;
        }

        go(function() use ($service, $action, $request, $response) {
            try {
                $client = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);

                // Parse microservice URL
                $url = parse_url($this->microservices[$service]);

                if (!$client->connect($url['host'], $url['port'], 1.0)) {
                    $response->status(503);
                    $response->end('Service unavailable');
                    return;
                }

                // Prepare request data
                $requestData = [
                    'action' => $action,
                    'method' => $request->server['request_method'],
                    'headers' => $request->header ?? [],
                    'data' => $request->post ?? $request->get ?? [],
                    'body' => $request->rawContent() ?? ''
                ];

                $client->send(json_encode($requestData));
                $result = $client->recv();
                $client->close();

                if ($result === false) {
                    $response->status(500);
                    $response->end('Service error');
                    return;
                }

                $serviceResponse = json_decode($result, true);

                $response->status($serviceResponse['status'] ?? 200);
                $response->header('Content-Type', $serviceResponse['content_type'] ?? 'application/json');
                $response->end($serviceResponse['body'] ?? '');

            } catch (Exception $e) {
                $response->status(500);
                $response->end('Internal server error: ' . $e->getMessage());
            }
        });
    }

    public function start()
    {
        $this->server->start();
    }
}

// Start the main server
$mainServer = new MainServer();
$mainServer->start();