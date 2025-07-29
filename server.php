<?php
/**
 * OpenSwoole Ultra-Low Latency Microservices Hello World
 * Features: Protocol switching, connection pooling, async I/O
 */

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server as WebSocketServer;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

class MicroserviceServer
{
    private WebSocketServer $server;
    private array $serviceRegistry = [];
    private array $connectionPool = [];
    private array $metrics = ['requests' => 0, 'latency' => []];

    public function __construct(string $host = '0.0.0.0', int $port = 80)
    {
        $this->server = new WebSocketServer($host, $port);

        // Ultra-low latency configuration
        $this->server->set([
            'worker_num' => OpenSwoole\Util::getCPUNum() * 2,
            'reactor_num' => OpenSwoole\Util::getCPUNum(),
            'max_coroutine' => 100000,
            'enable_coroutine' => true,
            'hook_flags' => SWOOLE_HOOK_ALL,
            'open_tcp_nodelay' => true,
            'tcp_fastopen' => true,
            'open_cpu_affinity' => true,
            'tcp_defer_accept' => 5,
            'heartbeat_check_interval' => 30,
            'heartbeat_idle_time' => 60,
            'buffer_output_size' => 32 * 1024 * 1024,
            'socket_buffer_size' => 128 * 1024 * 1024,
        ]);

        $this->registerEventHandlers();
        $this->registerServices();
    }

    private function registerEventHandlers(): void
    {
        // HTTP Request Handler (Protocol Detection)
        $this->server->on('request', [$this, 'handleHttpRequest']);

        // WebSocket Handlers
        $this->server->on('open', [$this, 'handleWebSocketOpen']);
        $this->server->on('message', [$this, 'handleWebSocketMessage']);
        $this->server->on('close', [$this, 'handleWebSocketClose']);

        // Server lifecycle
        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('workerStart', [$this, 'onWorkerStart']);
    }

    private function registerServices(): void
    {
        $this->serviceRegistry = [
            'hello' => new HelloService(),
            'user' => new UserService(),
            'metrics' => new MetricsService($this->metrics),
        ];
    }

    public function handleHttpRequest(Request $request, Response $response): void
    {
        $startTime = microtime(true);

        // Enable CORS for browser testing
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type');

        if ($request->server['request_method'] === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return;
        }

        $path = $request->server['request_uri'];
        $method = $request->server['request_method'];

        Coroutine::create(function() use ($request, $response, $path, $method, $startTime) {
            try {
                $result = $this->routeRequest($path, $method, $request);

                $response->header('Content-Type', 'application/json');
                $response->header('X-Response-Time', (microtime(true) - $startTime) * 1000 . 'ms');
                $response->status(200);
                $response->end(json_encode($result));

                $this->recordMetrics($startTime);
            } catch (Exception $e) {
                $response->status(500);
                $response->end(json_encode(['error' => $e->getMessage()]));
            }
        });
    }

    public function handleWebSocketOpen($server, $request): void
    {
        echo "WebSocket connection opened: {$request->fd}\n";
        $server->push($request->fd, json_encode([
            'type' => 'welcome',
            'message' => 'Connected to OpenSwoole Microservices',
            'fd' => $request->fd
        ]));
    }

    public function handleWebSocketMessage($server, $frame): void
    {
        $data = json_decode($frame->data, true);

        Coroutine::create(function() use ($server, $frame, $data) {
            try {
                $result = $this->processWebSocketMessage($data);
                $server->push($frame->fd, json_encode($result));
            } catch (Exception $e) {
                $server->push($frame->fd, json_encode(['error' => $e->getMessage()]));
            }
        });
    }

    public function handleWebSocketClose($server, $fd): void
    {
        echo "WebSocket connection closed: {$fd}\n";
    }

    private function routeRequest(string $path, string $method, Request $request): array
    {
        $segments = explode('/', trim($path, '/'));
        $serviceName = $segments[0] ?? 'hello';
        $action = $segments[1] ?? 'index';

        if (!isset($this->serviceRegistry[$serviceName])) {
            throw new Exception("Service '{$serviceName}' not found");
        }

        $service = $this->serviceRegistry[$serviceName];
        $methodName = strtolower($method) . ucfirst($action);

        if (!method_exists($service, $methodName)) {
            $methodName = 'index';
        }

        return $service->$methodName($request);
    }

    private function processWebSocketMessage(array $data): array
    {
        $service = $data['service'] ?? 'hello';
        $action = $data['action'] ?? 'index';
        $params = $data['params'] ?? [];

        if (!isset($this->serviceRegistry[$service])) {
            throw new Exception("Service '{$service}' not found");
        }

        $serviceInstance = $this->serviceRegistry[$service];
        $method = 'ws' . ucfirst($action);

        if (!method_exists($serviceInstance, $method)) {
            $method = 'wsIndex';
        }

        return $serviceInstance->$method($params);
    }

    private function recordMetrics(float $startTime): void
    {
        $this->metrics['requests']++;
        $latency = (microtime(true) - $startTime) * 1000;
        $this->metrics['latency'][] = $latency;

        // Keep only last 1000 latency measurements
        if (count($this->metrics['latency']) > 1000) {
            array_shift($this->metrics['latency']);
        }
    }

    public function onStart($server): void
    {
        echo "OpenSwoole Microservices Server started at http://0.0.0.0:80\n";
        echo "WebSocket endpoint: ws://0.0.0.0:80\n";
        echo "Workers: {$server->setting['worker_num']}\n";
        echo "Max Coroutines: {$server->setting['max_coroutine']}\n";
    }

    public function onWorkerStart($server, $workerId): void
    {
        echo "Worker {$workerId} started\n";
    }

    public function start(): void
    {
        $this->server->start();
    }
}

// Service Classes
class HelloService
{
    public function index($request): array
    {
        return [
            'service' => 'hello',
            'message' => 'Hello World from OpenSwoole Microservices!',
            'timestamp' => date('Y-m-d H:i:s'),
            'worker_id' => Coroutine::getCid(),
            'memory_usage' => memory_get_usage(true),
        ];
    }

    public function getName($request): array
    {
        $name = $request->get['name'] ?? 'World';
        return [
            'service' => 'hello',
            'message' => "Hello, {$name}!",
            'personalized' => true,
        ];
    }

    public function postGreeting($request): array
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $name = $data['name'] ?? 'Anonymous';
        $greeting = $data['greeting'] ?? 'Hello';

        return [
            'service' => 'hello',
            'message' => "{$greeting}, {$name}!",
            'custom_greeting' => true,
        ];
    }

    public function wsIndex($params): array
    {
        return [
            'type' => 'response',
            'service' => 'hello',
            'message' => 'Hello from WebSocket!',
            'params' => $params,
        ];
    }
}

class UserService
{
    private array $users = [
        1 => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        2 => ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ];

    public function index($request): array
    {
        return [
            'service' => 'user',
            'users' => array_values($this->users),
            'count' => count($this->users),
        ];
    }

    public function getShow($request): array
    {
        $id = (int)($request->get['id'] ?? 1);
        $user = $this->users[$id] ?? null;

        if (!$user) {
            throw new Exception("User {$id} not found");
        }

        return [
            'service' => 'user',
            'user' => $user,
        ];
    }

    public function wsIndex($params): array
    {
        return [
            'type' => 'response',
            'service' => 'user',
            'users' => array_values($this->users),
        ];
    }
}

class MetricsService
{
    private array $metrics;

    public function __construct(array &$metrics)
    {
        $this->metrics = &$metrics;
    }

    public function index($request): array
    {
        $avgLatency = !empty($this->metrics['latency'])
            ? array_sum($this->metrics['latency']) / count($this->metrics['latency'])
            : 0;

        return [
            'service' => 'metrics',
            'requests_total' => $this->metrics['requests'],
            'average_latency_ms' => round($avgLatency, 2),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'coroutines_active' => Coroutine::stats()['coroutine_num'] ?? 0,
        ];
    }

    public function wsIndex($params): array
    {
        return [
            'type' => 'metrics',
            'data' => $this->index(null),
        ];
    }
}

// Start the server
if (php_sapi_name() === 'cli') {
    $server = new MicroserviceServer();
    $server->start();
} else {
    echo "This script must be run from the command line\n";
}

// Example usage in comments:
/*
HTTP Endpoints:
- GET  http://localhost:9501/hello
- GET  http://localhost:9501/hello/name?name=John
- POST http://localhost:9501/hello/greeting (JSON: {"name": "John", "greeting": "Hi"})
- GET  http://localhost:9501/user
- GET  http://localhost:9501/user/show?id=1
- GET  http://localhost:9501/metrics

WebSocket:
- Connect to ws://localhost:9501
- Send: {"service": "hello", "action": "index", "params": {}}
- Send: {"service": "user", "action": "index", "params": {}}
- Send: {"service": "metrics", "action": "index", "params": {}}

Run with: php server.php
*/