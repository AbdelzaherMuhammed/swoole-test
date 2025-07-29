#!/bin/bash

# Performance Testing Script for Swoole Application
# Usage: bash performance_test.sh [server_ip]

SERVER=${1:-"localhost"}
PORT=${2:-"80"}
ENDPOINT="http://${SERVER}:${PORT}/hello"

echo "=== Performance Testing Suite ==="
echo "Testing endpoint: $ENDPOINT"
echo "Server: $SERVER"
echo "Port: $PORT"
echo ""

# Check if wrk is installed
if ! command -v wrk &> /dev/null; then
    echo "wrk is not installed. Installing..."
    # Install wrk
    if command -v yum &> /dev/null; then
        yum install -y git gcc make
    elif command -v apt-get &> /dev/null; then
        apt-get update && apt-get install -y git gcc make
    fi

    git clone https://github.com/wg/wrk.git
    cd wrk && make && sudo cp wrk /usr/local/bin/
    cd .. && rm -rf wrk
fi

echo "=== Test 1: Baseline Performance ==="
echo "Running light load test..."
wrk -t4 -c10 -d10s $ENDPOINT
echo ""

echo "=== Test 2: Medium Load ==="
echo "Running medium load test..."
wrk -t8 -c50 -d15s $ENDPOINT
echo ""

echo "=== Test 3: High Load ==="
echo "Running high load test..."
wrk -t16 -c100 -d20s $ENDPOINT
echo ""

echo "=== Test 4: Extreme Load ==="
echo "Running extreme load test..."
wrk -t32 -c200 -d30s $ENDPOINT
echo ""

echo "=== Latency Distribution Test ==="
echo "Getting detailed latency statistics..."
wrk -t16 -c100 -d30s --latency $ENDPOINT
echo ""

# Test with POST data
echo "=== POST Request Test ==="
echo "Testing POST requests with data..."
wrk -t8 -c50 -d10s -s - $ENDPOINT << 'EOF'
wrk.method = "POST"
wrk.body = "name=TestUser"
wrk.headers["Content-Type"] = "application/x-www-form-urlencoded"
EOF
echo ""

echo "=== System Resource Usage ==="
echo "Current system load:"
uptime
echo ""
echo "Memory usage:"
free -h
echo ""
echo "CPU info:"
lscpu | grep "CPU(s):"
echo ""

echo "=== Performance Testing Complete ==="
echo "Tips for optimization:"
echo "1. If latency > 10ms, check network configuration"
echo "2. If requests/sec < 5000, optimize server settings"
echo "3. Monitor CPU and memory usage during tests"
echo "4. Consider using HTTP/2 or connection pooling for production"