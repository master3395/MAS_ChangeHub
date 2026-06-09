#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/config.php"
SNAPSHOT_SCRIPT="$SCRIPT_DIR/snapshot-manager.php"
LOG_DIR="$SCRIPT_DIR/logs"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}Apply Contabo snapshot schedule from config.php${NC}"

if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}Configuration file not found: $CONFIG_FILE${NC}"
    exit 1
fi

SNAPSHOT_TIME=$(grep "define('SNAPSHOT_TIME'" "$CONFIG_FILE" | sed -n "s/.*'\([0-9:]*\)'.*/\1/p")
if [ -z "$SNAPSHOT_TIME" ]; then
    echo -e "${RED}SNAPSHOT_TIME not found in config.php${NC}"
    exit 1
fi

AUTO_APPLY=$(grep "define('AUTO_APPLY_SCHEDULE'" "$CONFIG_FILE" | grep -o 'true\|false')
if [ "$AUTO_APPLY" != "true" ]; then
    echo -e "${YELLOW}AUTO_APPLY_SCHEDULE is false${NC}"
    exit 0
fi

hour=$(echo "$SNAPSHOT_TIME" | cut -d':' -f1)
minute=$(echo "$SNAPSHOT_TIME" | cut -d':' -f2)
hour=${hour#0}
minute=${minute#0}
[ -z "$hour" ] && hour=0
[ -z "$minute" ] && minute=0

mkdir -p "$LOG_DIR"
crontab -l 2>/dev/null | grep -v "MAS_ChangeHub/contabo/snapshot-manager.php" | grep -v "contabo-snapshots/snapshot-manager.php" | crontab -
(crontab -l 2>/dev/null; echo "$minute $hour * * * /usr/bin/php $SNAPSHOT_SCRIPT >> $LOG_DIR/cron.log 2>&1  # Contabo snapshot (MAS ChangeHub)") | crontab -

echo -e "${GREEN}Schedule applied: $SNAPSHOT_TIME (Europe/Oslo)${NC}"
crontab -l | grep "MAS_ChangeHub/contabo/snapshot-manager.php" || true
