#!/bin/bash

# Alibaba Cloud PHP/Swoole Performance Optimization Script
# Run as root: sudo bash optimize_cloud.sh

echo "=== Alibaba Cloud Performance Optimization ==="

# 1. Update system packages
echo "Updating system packages..."
yum update -y || apt-get update -y

# 2. Install performance tools
echo "Installing performance tools..."
yum install -y htop iotop nethogs || apt-get install -y htop iotop nethogs

# 3. Optimize kernel parameters for high performance
echo "Optimizing kernel parameters..."
cat >> /etc/sysctl.conf << 'EOF'

# Network optimizations for high performance
net.core.rmem_max = 134217728
net.core.wmem_max = 134217728
net.core.netdev_max_backlog = 5000
net.core.somaxconn = 65535
net.ipv4.tcp_rmem = 4096 65536 134217728
net.ipv4.tcp_wmem = 4096 65536 134217728
net.ipv4.tcp_congestion_control = bbr
net.ipv4.tcp_fastopen = 3
net.ipv4.tcp_no_metrics_save = 1
net.ipv4.tcp_low_latency = 1
net.ipv4.tcp_timestamps = 0
net.ipv4.tcp_sack = 1
net.ipv4.tcp_window_scaling = 1
net.ipv4.tcp_keepalive_time = 600
net.ipv4.tcp_keepalive_intvl = 60
net.ipv4.tcp_keepalive_probes = 10
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_tw_reuse = 1
net.ipv4.ip_local_port_range = 1024 65535

# File system optimizations
fs.file-max = 1000000
vm.swappiness = 1
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5

EOF

# Apply sysctl changes
sysctl -p

# 4. Optimize file limits
echo "Optimizing file limits..."
cat >> /etc/security/limits.conf << 'EOF'

# High performance limits
* soft nofile 1000000
* hard nofile 1000000
* soft nproc 1000000
* hard nproc 1000000

EOF

# 5. PHP optimizations
echo "Optimizing PHP configuration..."
PHP_INI=$(php --ini | grep "Loaded Configuration File" | cut -d':' -f2 | xargs)

if [ -f "$PHP_INI" ]; then
    # Backup original
    cp "$PHP_INI" "${PHP_INI}.backup"

    # Apply optimizations
    cat >> "$PHP_INI" << 'EOF'

; Performance optimizations
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=0
opcache.fast_shutdown=1
opcache.huge_code_pages=1

; Memory optimizations
memory_limit=512M
max_execution_time=300

; Session optimizations
session.save_handler=files
session.gc_maxlifetime=86400

EOF
else
    echo "PHP configuration file not found, skipping PHP optimizations"
fi

# 6. Create systemd service for the application
echo "Creating systemd service..."
cat > /etc/systemd/system/swoole-app.service << 'EOF'
[Unit]
Description=Swoole High Performance Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/your-app
ExecStart=/usr/bin/php /var/www/your-app/start_optimized.php
Restart=always
RestartSec=3
StandardOutput=journal
StandardError=journal

# Performance settings
LimitNOFILE=1000000
LimitNPROC=1000000

[Install]
WantedBy=multi-user.target
EOF

# 7. CPU governor optimization
echo "Setting CPU governor to performance..."
echo performance | tee /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor

# 8. Disable unnecessary services
echo "Disabling unnecessary services..."
systemctl disable postfix || true
systemctl disable sendmail || true
systemctl disable bluetooth || true

echo "=== Optimization Complete ==="
echo "Please reboot the system for all changes to take effect"
echo "After reboot, run: systemctl enable swoole-app && systemctl start swoole-app"