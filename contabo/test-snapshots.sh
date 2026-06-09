#!/bin/bash

# Contabo Snapshot Manager - Test Script
# This script tests the snapshot management functionality

echo "Testing Contabo Snapshot Manager..."
echo "=================================="
echo ""

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP is not installed or not in PATH"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
echo "PHP Version: $PHP_VERSION"

# Check if required PHP extensions are available
echo "Checking PHP extensions..."
php -m | grep -E "(curl|json)" > /dev/null
if [ $? -eq 0 ]; then
    echo "✓ Required PHP extensions (curl, json) are available"
else
    echo "✗ Missing required PHP extensions (curl, json)"
    exit 1
fi

# Check if config file exists and is readable
if [ -f "/home/contabo-snapshots/config.php" ]; then
    echo "✓ Configuration file exists"
else
    echo "✗ Configuration file not found"
    exit 1
fi

# Check if main script exists and is executable
if [ -f "/home/contabo-snapshots/snapshot-manager.php" ]; then
    echo "✓ Main script exists"
    if [ -x "/home/contabo-snapshots/snapshot-manager.php" ]; then
        echo "✓ Main script is executable"
    else
        echo "✗ Main script is not executable"
        chmod +x /home/contabo-snapshots/snapshot-manager.php
        echo "  Fixed: Made script executable"
    fi
else
    echo "✗ Main script not found"
    exit 1
fi

# Check if log directory exists
if [ -d "/home/contabo-snapshots/logs" ]; then
    echo "✓ Log directory exists"
else
    echo "✗ Log directory not found, creating..."
    mkdir -p /home/contabo-snapshots/logs
    echo "  Created log directory"
fi

# Test configuration loading
echo ""
echo "Testing configuration loading..."
php -r "
define('CONTABO_SNAPSHOT_INIT', true);
require_once '/home/contabo-snapshots/config.php';
echo '✓ Configuration loaded successfully' . PHP_EOL;
echo '  Client ID: ' . CONTABO_CLIENT_ID . PHP_EOL;
echo '  API Base URL: ' . CONTABO_API_BASE_URL . PHP_EOL;
echo '  Target Instances: ' . implode(', ', INSTANCE_NAMES) . PHP_EOL;
echo '  Max Snapshots: ' . MAX_SNAPSHOTS_PER_INSTANCE . PHP_EOL;
"

if [ $? -eq 0 ]; then
    echo "✓ Configuration test passed"
else
    echo "✗ Configuration test failed"
    exit 1
fi

# Test API connectivity (dry run)
echo ""
echo "Testing API connectivity..."
echo "Note: This will attempt to authenticate with Contabo API"
echo "Make sure your credentials are correct in config.php"
echo ""

# Run the script in test mode (you can modify the script to add a --test flag)
echo "Running snapshot manager (this will make actual API calls)..."
echo "Press Ctrl+C within 5 seconds to cancel, or wait to continue..."
sleep 5

# Run the actual script
php /home/contabo-snapshots/snapshot-manager.php

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Snapshot manager completed successfully"
    echo ""
    echo "Check the logs for details:"
    echo "  Main log: /home/contabo-snapshots/logs/snapshot-manager.log"
    echo "  Error log: /home/contabo-snapshots/logs/snapshot-errors.log"
else
    echo ""
    echo "✗ Snapshot manager failed"
    echo "Check the error log: /home/contabo-snapshots/logs/snapshot-errors.log"
    exit 1
fi

echo ""
echo "Test completed!"
