#!/bin/bash

# Contabo Snapshot Manager - Test Script

MAS_CHANGEHUB_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTABO_DIR="$MAS_CHANGEHUB_ROOT/contabo"

echo "Testing Contabo Snapshot Manager..."
echo "=================================="
echo ""

if ! command -v php &> /dev/null; then
    echo "ERROR: PHP is not installed or not in PATH"
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
echo "PHP Version: $PHP_VERSION"

echo "Checking PHP extensions..."
if php -m | grep -E "(curl|json)" > /dev/null; then
    echo "OK: Required PHP extensions (curl, json) are available"
else
    echo "ERROR: Missing required PHP extensions (curl, json)"
    exit 1
fi

if [ -f "$CONTABO_DIR/config.php" ]; then
    echo "OK: config.php found"
else
    echo "ERROR: config.php not found at $CONTABO_DIR/config.php"
    exit 1
fi

if [ -f "$CONTABO_DIR/snapshot-manager.php" ]; then
  if [ -x "$CONTABO_DIR/snapshot-manager.php" ]; then
    echo "OK: snapshot-manager.php is executable"
  else
    chmod +x "$CONTABO_DIR/snapshot-manager.php"
    echo "OK: Made snapshot-manager.php executable"
  fi
else
    echo "ERROR: snapshot-manager.php not found"
    exit 1
fi

if [ -d "$CONTABO_DIR/logs" ]; then
    echo "OK: logs directory exists"
else
    mkdir -p "$CONTABO_DIR/logs"
    echo "OK: Created logs directory"
fi

echo ""
echo "Testing configuration loading..."
php -r "define('CONTABO_SNAPSHOT_INIT', true); require_once '$CONTABO_DIR/config.php'; echo 'OK: Configuration loaded successfully\n';"

echo ""
echo "Running snapshot manager (dry run via main script)..."
php "$CONTABO_DIR/snapshot-manager.php"
status=$?
echo ""
if [ $status -eq 0 ]; then
    echo "OK: Snapshot manager completed"
    echo "  Main log: $CONTABO_DIR/logs/snapshot-manager.log"
    echo "  Error log: $CONTABO_DIR/logs/snapshot-errors.log"
else
    echo "ERROR: Snapshot manager failed (exit $status)"
    echo "Check: $CONTABO_DIR/logs/snapshot-errors.log"
    exit $status
fi
