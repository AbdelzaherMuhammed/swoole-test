<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/models/HelloModel.php';
require_once __DIR__ . '/controllers/HelloController.php';
require_once __DIR__ . '/services/HelloService.php';

use Swoole\Server;

class HelloMicroservice
{
    private $server;
    private $controller;

    public function __construct()
    {
        $this->server = new Server("127.0.0.1", 9003, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->controller = new HelloController();

        $this->configureServer();
        $this->setupHandlers();
    }

    private function configureServer()
    {
        $this->server->set([
            'worker_num' => OpenSwoole\Util::getCPUNum() * 2,
            'daemonize' => false,
            'max_request' => 1000,
            'dispatch_mode' => 2,
            'enable_coroutine' => true,
            'log_file' => __DIR__ . '/logs/hello.log',
        ]);
    }

    private function setupHandlers()
    {
        $this->server->on('connect', function ($server, $fd) {
            echo "Hello Microservice: Client {$fd} connected\n";
        });

        $this->server->on('receive', function ($server, $fd, $reactorId, $data) {
            $this->handleRequest($server, $fd, $data);
        });

        $this->server->on('close', function ($server, $fd) {
            echo "Hello Microservice: Client {$fd} closed\n";
        });

        $this->server->on('start', function ($server) {
            echo "Hello Microservice started on 127.0.0.1:9003\n";
            echo "Master PID: {$server->master_pid}\n";
        });
    }

    private function handleRequest($server, $fd, $data)
    {
        try {
            $request = json_decode($data, true);

            if (!$request || !isset($request['action'])) {
                $this->sendError($server, $fd, 'Invalid request format');
                return;
            }

            $action = $request['action'];
            $method = $request['method'] ?? 'GET';
            $requestData = $request['data'] ?? [];

            switch ($action) {
                case 'hello':
                    $result = $this->controller->hello($method, $requestData);
                    break;
                case 'greet':
                    $result = $this->controller->greet($method, $requestData);
                    break;
                default:
                    $result = [
                        'status' => 404,
                        'body' => json_encode(['error' => 'Action not found']),
                        'content_type' => 'application/json'
                    ];
            }

            $server->send($fd, json_encode($result));

        } catch (Exception $e) {
            $this->sendError($server, $fd, 'Server error: ' . $e->getMessage());
        }
    }

    private function sendError($server, $fd, $message)
    {
        $response = [
            'status' => 500,
            'body' => json_encode(['error' => $message]),
            'content_type' => 'application/json'
        ];
        $server->send($fd, json_encode($response));
    }

    public function start()
    {
        $this->server->start();
    }
}

// Start the Hello microservice
$helloService = new HelloMicroservice();
$helloService->start();
