<?php


class HelloController
{
    private $helloService;

    public function __construct()
    {
        $this->helloService = new HelloService();
    }

    public function hello($method, $data)
    {
        try {
            switch ($method) {
                case 'GET':
                    $message = $this->helloService->getHelloMessage();
                    break;
                case 'POST':
                    $name = $data['name'] ?? 'World';
                    $message = $this->helloService->getCustomHello($name);
                    break;
                default:
                    return [
                        'status' => 405,
                        'body' => json_encode(['error' => 'Method not allowed']),
                        'content_type' => 'application/json'
                    ];
            }

            return [
                'status' => 200,
                'body' => json_encode([
                    'success' => true,
                    'message' => $message,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'service' => 'hello-microservice'
                ]),
                'content_type' => 'application/json'
            ];

        } catch (Exception $e) {
            return [
                'status' => 500,
                'body' => json_encode(['error' => $e->getMessage()]),
                'content_type' => 'application/json'
            ];
        }
    }

    public function greet($method, $data)
    {
        try {
            if ($method !== 'POST') {
                return [
                    'status' => 405,
                    'body' => json_encode(['error' => 'Method not allowed']),
                    'content_type' => 'application/json'
                ];
            }

            $name = $data['name'] ?? '';
            $language = $data['language'] ?? 'en';

            if (empty($name)) {
                return [
                    'status' => 400,
                    'body' => json_encode(['error' => 'Name is required']),
                    'content_type' => 'application/json'
                ];
            }

            $greeting = $this->helloService->getGreeting($name, $language);

            return [
                'status' => 200,
                'body' => json_encode([
                    'success' => true,
                    'greeting' => $greeting,
                    'name' => $name,
                    'language' => $language,
                    'service' => 'hello-microservice'
                ]),
                'content_type' => 'application/json'
            ];

        } catch (Exception $e) {
            return [
                'status' => 500,
                'body' => json_encode(['error' => $e->getMessage()]),
                'content_type' => 'application/json'
            ];
        }
    }
}