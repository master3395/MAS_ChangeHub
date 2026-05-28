#!/bin/bash

# Apply Schedule from Config File
# This script reads CUSTOM_SCHEDULE_TIMES from snapshot_config.conf and sets up cron jobs

SCRIPT_DIR="/home/MAS_ChangeHub"
CONFIG_FILE="$SCRIPT_DIR/snapshot_config.conf"
SNAPSHOT_SCRIPT="$SCRIPT_DIR/website_snapshot.sh"
LOG_FILE="$SCRIPT_DIR/snapshot.log"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║        APPLY SCHEDULE FROM CONFIG FILE                       ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo

# Load configuration
if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}❌ Configuration file not found: $CONFIG_FILE${NC}"
    exit 1
fi

source "$CONFIG_FILE"

# Check if AUTO_APPLY_SCHEDULE is enabled
if [ "$AUTO_APPLY_SCHEDULE" != "true" ]; then
    echo -e "${YELLOW}⚠️  AUTO_APPLY_SCHEDULE is set to 'false' in config${NC}"
    echo -e "${CYAN}ℹ️  To enable, edit $CONFIG_FILE and set:${NC}"
    echo -e "${CYAN}   AUTO_APPLY_SCHEDULE=true${NC}"
    echo
    echo -e "${YELLOW}💡 Current schedule will remain unchanged.${NC}"
    echo -e "${YELLOW}   Use ./schedule_manager.sh to manage schedules manually.${NC}"
    exit 0
fi

# Check if CUSTOM_SCHEDULE_TIMES is set
if [ -z "$CUSTOM_SCHEDULE_TIMES" ]; then
    echo -e "${RED}❌ CUSTOM_SCHEDULE_TIMES is not set in config${NC}"
    echo -e "${CYAN}ℹ️  Please add schedule times to $CONFIG_FILE${NC}"
    echo -e "${CYAN}   Example: CUSTOM_SCHEDULE_TIMES=\"09:00 13:00 17:00 21:00\"${NC}"
    exit 1
fi

echo -e "${YELLOW}📋 Reading configuration...${NC}"
echo -e "${GREEN}✓${NC} AUTO_APPLY_SCHEDULE: $AUTO_APPLY_SCHEDULE"
echo -e "${GREEN}✓${NC} CUSTOM_SCHEDULE_TIMES: $CUSTOM_SCHEDULE_TIMES"
echo -e "${GREEN}✓${NC} DOMAIN_SELECTION_MODE: $DOMAIN_SELECTION_MODE"
echo

# Count domains
case "$DOMAIN_SELECTION_MODE" in
    "main")
        domain_count=1
        ;;
    "custom")
        domain_count=${#CUSTOM_DOMAINS[@]}
        ;;
    *)
        domain_count=${#WEBSITES[@]}
        ;;
esac

echo -e "${CYAN}ℹ️  Will snapshot $domain_count domain(s) per run${NC}"
echo

# Remove existing archive snapshot cron jobs
echo -e "${YELLOW}🔄 Removing existing archive snapshot cron jobs...${NC}"
crontab -l 2>/dev/null | grep -v "MAS_ChangeHub/website_snapshot.sh" | crontab -

# Add new cron jobs based on CUSTOM_SCHEDULE_TIMES
echo -e "${YELLOW}📅 Adding new schedule...${NC}"

count=0
for time in $CUSTOM_SCHEDULE_TIMES; do
    # Extract hour and minute
    hour=$(echo "$time" | cut -d':' -f1)
    minute=$(echo "$time" | cut -d':' -f2)
    
    # Remove leading zeros for validation
    hour=${hour#0}
    minute=${minute#0}
    
    # Validate time format
    if [[ ! "$hour" =~ ^[0-9]+$ ]] || [[ ! "$minute" =~ ^[0-9]+$ ]]; then
        echo -e "${RED}❌ Invalid time format: $time${NC}"
        continue
    fi
    
    # Validate ranges
    if [ "$hour" -lt 0 ] || [ "$hour" -gt 23 ] || [ "$minute" -lt 0 ] || [ "$minute" -gt 59 ]; then
        echo -e "${RED}❌ Invalid time range: $time${NC}"
        continue
    fi
    
    # Add cron entry
    (crontab -l 2>/dev/null; echo "$minute $hour * * * $SNAPSHOT_SCRIPT # Archive snapshot (config-based, logs internally)") | crontab -
    ((count++))
    echo -e "${GREEN}✓${NC} Added: $time GMT+2"
done

if [ $count -eq 0 ]; then
    echo -e "${RED}❌ No valid schedule times added${NC}"
    exit 1
fi

echo
echo -e "${GREEN}✅ Schedule successfully applied from config file!${NC}"
echo
echo -e "${CYAN}📊 Summary:${NC}"
echo -e "   • Schedule runs: $count time(s) per day"
echo -e "   • Domains per run: $domain_count"
echo -e "   • Total snapshots per day: $((count * domain_count))"
echo

echo -e "${YELLOW}📋 New Cron Jobs:${NC}"
crontab -l | grep "MAS_ChangeHub"

echo
echo -e "${CYAN}💡 Tips:${NC}"
echo -e "   • View logs: tail -f $LOG_FILE"
echo -e "   • Check status: ./check_snapshots.sh"
echo -e "   • Test now: ./website_snapshot.sh"
echo -e "   • Edit config: nano $CONFIG_FILE"
echo
echo -e "${GREEN}✓ Done!${NC}"

