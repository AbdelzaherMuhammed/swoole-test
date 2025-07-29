<?php
/**
 * Ultra-simple startup script - Single process, maximum performance
 * Usage: php start_optimized.php
 */

// Create logs directory
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

echo "Starting ultra-optimized single-process server...\n";

// Include and start the optimized server
require_once __DIR__ . '/server.php';