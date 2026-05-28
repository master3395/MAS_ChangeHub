#!/bin/bash

# Schedule Manager for Internet Archive Snapshot System
# Allows easy configuration of snapshot frequency

SCRIPT_DIR="/home/MAS_ChangeHub"
CONFIG_FILE="$SCRIPT_DIR/snapshot_config.conf"
SNAPSHOT_SCRIPT="$SCRIPT_DIR/website_snapshot.sh"
LOG_FILE="$SCRIPT_DIR/snapshot.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Function to display header
show_header() {
    clear
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║           SNAPSHOT SCHEDULE MANAGER                          ║${NC}"
    echo -e "${CYAN}║        Configure Archive Snapshot Frequency                  ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo
}

# Function to get current schedule
get_current_schedule() {
    echo -e "${YELLOW}📅 Current Schedule:${NC}"
    echo "==================="
    
    # Get current cron jobs for MAS_ChangeHub
    local cron_lines=$(crontab -l 2>/dev/null | grep "MAS_ChangeHub/website_snapshot.sh")
    
    if [ -z "$cron_lines" ]; then
        echo -e "${RED}❌ No scheduled snapshots found${NC}"
        return 1
    fi
    
    echo "$cron_lines" | while read line; do
        # Extract time from cron expression
        local hour=$(echo "$line" | awk '{print $2}')
        local minute=$(echo "$line" | awk '{print $1}')
        echo -e "${GREEN}✓${NC} Scheduled at ${CYAN}$(printf "%02d:%02d" $hour $minute) GMT+2${NC}"
    done
    
    local count=$(echo "$cron_lines" | wc -l)
    echo
    echo -e "${BLUE}📊 Running $count time(s) per day${NC}"
}

# Function to remove all archive snapshot cron jobs
remove_all_schedules() {
    crontab -l 2>/dev/null | grep -v "MAS_ChangeHub/website_snapshot.sh" | crontab -
}

# Function to add a cron schedule
add_cron_schedule() {
    local minute=$1
    local hour=$2
    local description=$3
    
    (crontab -l 2>/dev/null; echo "$minute $hour * * * $SNAPSHOT_SCRIPT >> $LOG_FILE 2>&1  # $description") | crontab -
}

# Function to set schedule based on frequency
set_schedule() {
    local frequency=$1
    
    echo -e "${YELLOW}🔄 Updating schedule...${NC}"
    
    # Remove existing schedules
    remove_all_schedules
    
    case $frequency in
        1)
            # 1x daily: 23:00 GMT+2
            add_cron_schedule "0" "23" "Archive snapshot 1x daily"
            echo -e "${GREEN}✅ Schedule set: Once daily at 23:00 GMT+2${NC}"
            ;;
        2)
            # 2x daily: 11:00, 23:00 GMT+2 (12 hours apart)
            add_cron_schedule "0" "11" "Archive snapshot 2x daily (morning)"
            add_cron_schedule "0" "23" "Archive snapshot 2x daily (evening)"
            echo -e "${GREEN}✅ Schedule set: Twice daily at 11:00 and 23:00 GMT+2${NC}"
            ;;
        3)
            # 3x daily: 07:00, 15:00, 23:00 GMT+2 (8 hours apart)
            add_cron_schedule "0" "7" "Archive snapshot 3x daily (morning)"
            add_cron_schedule "0" "15" "Archive snapshot 3x daily (afternoon)"
            add_cron_schedule "0" "23" "Archive snapshot 3x daily (evening)"
            echo -e "${GREEN}✅ Schedule set: 3 times daily at 07:00, 15:00, and 23:00 GMT+2${NC}"
            ;;
        4)
            # 4x daily: 05:00, 11:00, 17:00, 23:00 GMT+2 (6 hours apart)
            add_cron_schedule "0" "5" "Archive snapshot 4x daily (early morning)"
            add_cron_schedule "0" "11" "Archive snapshot 4x daily (morning)"
            add_cron_schedule "0" "17" "Archive snapshot 4x daily (afternoon)"
            add_cron_schedule "0" "23" "Archive snapshot 4x daily (evening)"
            echo -e "${GREEN}✅ Schedule set: 4 times daily at 05:00, 11:00, 17:00, and 23:00 GMT+2${NC}"
            ;;
        6)
            # 6x daily: 05:00, 09:00, 13:00, 17:00, 21:00, 01:00 GMT+2 (4 hours apart)
            add_cron_schedule "0" "5" "Archive snapshot 6x daily (early morning)"
            add_cron_schedule "0" "9" "Archive snapshot 6x daily (morning)"
            add_cron_schedule "0" "13" "Archive snapshot 6x daily (early afternoon)"
            add_cron_schedule "0" "17" "Archive snapshot 6x daily (late afternoon)"
            add_cron_schedule "0" "21" "Archive snapshot 6x daily (evening)"
            add_cron_schedule "0" "1" "Archive snapshot 6x daily (night - next day)"
            echo -e "${GREEN}✅ Schedule set: 6 times daily at 05:00, 09:00, 13:00, 17:00, 21:00, and 01:00 GMT+2${NC}"
            ;;
        *)
            echo -e "${RED}❌ Invalid frequency${NC}"
            return 1
            ;;
    esac
    
    # Update config file
    sed -i "s/^SNAPSHOT_FREQUENCY=.*/SNAPSHOT_FREQUENCY=$frequency/" "$CONFIG_FILE"
    
    echo
    echo -e "${CYAN}ℹ️  Note: Internet Archive allows 1 snapshot per URL per hour.${NC}"
    echo -e "${CYAN}   Running too frequently may result in some snapshots being skipped.${NC}"
}

# Function to set custom schedule
set_custom_schedule() {
    echo -e "${YELLOW}🛠️  Custom Schedule Configuration${NC}"
    echo "================================="
    echo
    echo "Enter times in 24-hour format (HH:MM) separated by spaces"
    echo "Example: 06:00 12:00 18:00 23:00"
    echo
    echo -n -e "${BLUE}Times: ${NC}"
    read custom_times
    
    if [ -z "$custom_times" ]; then
        echo -e "${RED}❌ No times provided${NC}"
        return 1
    fi
    
    # Remove existing schedules
    remove_all_schedules
    
    # Add each custom time
    local count=0
    for time in $custom_times; do
        local hour=$(echo "$time" | cut -d':' -f1)
        local minute=$(echo "$time" | cut -d':' -f2)
        
        # Validate time format
        if [[ ! "$hour" =~ ^[0-9]{1,2}$ ]] || [[ ! "$minute" =~ ^[0-9]{2}$ ]]; then
            echo -e "${RED}❌ Invalid time format: $time${NC}"
            continue
        fi
        
        # Validate ranges
        if [ "$hour" -lt 0 ] || [ "$hour" -gt 23 ] || [ "$minute" -lt 0 ] || [ "$minute" -gt 59 ]; then
            echo -e "${RED}❌ Invalid time range: $time${NC}"
            continue
        fi
        
        add_cron_schedule "$minute" "$hour" "Archive snapshot custom schedule"
        ((count++))
        echo -e "${GREEN}✓${NC} Added: $time GMT+2"
    done
    
    if [ $count -gt 0 ]; then
        echo
        echo -e "${GREEN}✅ Custom schedule set with $count snapshot time(s)${NC}"
        sed -i "s/^SNAPSHOT_FREQUENCY=.*/SNAPSHOT_FREQUENCY=custom/" "$CONFIG_FILE"
    else
        echo -e "${RED}❌ No valid times added${NC}"
        return 1
    fi
}

# Function to disable all schedules
disable_schedules() {
    echo -e "${YELLOW}⚠️  Disabling all snapshot schedules...${NC}"
    remove_all_schedules
    echo -e "${GREEN}✅ All snapshot schedules disabled${NC}"
    echo -e "${CYAN}💡 To re-enable, run this script and choose a frequency${NC}"
}

# Main menu
main_menu() {
    while true; do
        show_header
        get_current_schedule
        echo
        echo -e "${YELLOW}📋 Schedule Options:${NC}"
        echo -e "${GREEN}1.${NC} Once daily (23:00 GMT+2)"
        echo -e "${GREEN}2.${NC} Twice daily (11:00, 23:00 GMT+2) - 12 hours apart"
        echo -e "${GREEN}3.${NC} 3 times daily (07:00, 15:00, 23:00 GMT+2) - 8 hours apart"
        echo -e "${GREEN}4.${NC} 4 times daily (05:00, 11:00, 17:00, 23:00 GMT+2) - 6 hours apart"
        echo -e "${GREEN}5.${NC} 6 times daily (05:00, 09:00, 13:00, 17:00, 21:00, 01:00 GMT+2) - 4 hours apart"
        echo -e "${GREEN}6.${NC} Custom schedule (specify your own times)"
        echo -e "${GREEN}7.${NC} Disable all schedules"
        echo -e "${GREEN}8.${NC} Test snapshot now (manual run)"
        echo -e "${RED}0.${NC} Exit"
        echo
        echo -n -e "${BLUE}Enter your choice [0-8]: ${NC}"
        read choice
        
        case $choice in
            1)
                set_schedule 1
                read -p "Press Enter to continue..."
                ;;
            2)
                set_schedule 2
                read -p "Press Enter to continue..."
                ;;
            3)
                set_schedule 3
                read -p "Press Enter to continue..."
                ;;
            4)
                set_schedule 4
                read -p "Press Enter to continue..."
                ;;
            5)
                set_schedule 6
                read -p "Press Enter to continue..."
                ;;
            6)
                set_custom_schedule
                read -p "Press Enter to continue..."
                ;;
            7)
                disable_schedules
                read -p "Press Enter to continue..."
                ;;
            8)
                echo -e "${YELLOW}🚀 Running snapshot now...${NC}"
                echo
                $SNAPSHOT_SCRIPT
                echo
                read -p "Press Enter to continue..."
                ;;
            0)
                echo -e "${GREEN}👋 Goodbye!${NC}"
                exit 0
                ;;
            *)
                echo -e "${RED}Invalid choice! Please try again.${NC}"
                read -p "Press Enter to continue..."
                ;;
        esac
    done
}

# Show quick status if run with --status flag
if [ "$1" = "--status" ]; then
    get_current_schedule
    exit 0
fi

# Show quick set if run with frequency argument
if [ "$1" = "--set" ] && [ -n "$2" ]; then
    set_schedule "$2"
    exit 0
fi

# Run main menu
main_menu

