<?php
/**
 * Startup script to run all microservices and main server
 * Usage: php start_services.php
 */

class ServiceManager
{
    private $services = [
        'hello' => [
            'path' => __DIR__ . '/microservices/hello/server.php',
            'port' => 9003,
            'name' => 'Hello Microservice'
        ]
    ];

    private $processes = [];

    public function __construct()
    {
        $this->createDirectories();
    }

    private function createDirectories()
    {
        $directories = [
            __DIR__ . '/logs',
            __DIR__ . '/ssl',
            __DIR__ . '/microservices/hello/logs'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                echo "Created directory: {$dir}\n";
            }
        }
    }

    public function startMicroservices()
    {
        echo "Starting microservices...\n";

        foreach ($this->services as $key => $service) {
            if (file_exists($service['path'])) {
                echo "Starting {$service['name']} on port {$service['port']}...\n";

                $process = proc_open(
                    "php {$service['path']}",
                    [
                        0 => ['pipe', 'r'],
                        1 => ['pipe', 'w'],
                        2 => ['pipe', 'w']
                    ],
                    $pipes,
                    dirname($service['path'])
                );

                if (is_resource($process)) {
                    $this->processes[$key] = [
                        'process' => $process,
                        'pipes' => $pipes,
                        'service' => $service
                    ];
                    echo "{$service['name']} started successfully\n";
                    usleep(500000); // Wait 0.5 seconds between starts
                } else {
                    echo "Failed to start {$service['name']}\n";
                }
            } else {
                echo "Service file not found: {$service['path']}\n";
            }
        }
    }

    public function startMainServer()
    {
        echo "\nStarting main HTTP/HTTPS server...\n";
        sleep(2); // Give microservices time to start

        // Start main server (this will block)
        require_once __DIR__ . '/server.php';
    }

    public function stopServices()
    {
        echo "\nStopping all services...\n";

        foreach ($this->processes as $key => $processInfo) {
            if (is_resource($processInfo['process'])) {
                // Close pipes
                foreach ($processInfo['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }

                // Terminate process
                proc_terminate($processInfo['process']);
                proc_close($processInfo['process']);

                echo "Stopped {$processInfo['service']['name']}\n";
            }
        }
    }

    public function run()
    {
        // Handle shutdown gracefully
        pcntl_signal(SIGTERM, [$this, 'stopServices']);
        pcntl_signal(SIGINT, [$this, 'stopServices']);

        $this->startMicroservices();
        $this->startMainServer();
    }
}

// Create SSL certificate if doesn't exist
if (!file_exists(__DIR__ . '/ssl/server.crt') || !file_exists(__DIR__ . '/ssl/server.key')) {
    echo "Creating self-signed SSL certificate...\n";
    $command = 'openssl req -x509 -newkey rsa:4096 -keyout ' . __DIR__ . '/ssl/server.key -out ' . __DIR__ . '/ssl/server.crt -days 365 -nodes -subj "/C=SA/ST=Jazan/L=Jizan/O=MyOrg/CN=localhost"';
    exec($command);
    echo "SSL certificate created.\n";
}

// Start all services
$manager = new ServiceManager();
$manager->run();