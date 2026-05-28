#!/bin/bash

# Archive-Snapshots CLI Menu System
# Interactive menu for managing website snapshots

SCRIPT_DIR="/home/MAS_ChangeHub"
LOG_FILE="$SCRIPT_DIR/snapshot.log"

# Colors for better display
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Function to display header
show_header() {
    clear
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                    ARCHIVE-SNAPSHOTS MENU                    ║${NC}"
    echo -e "${CYAN}║              Website Snapshot Management System              ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo
}

# Function to display menu options
show_menu() {
    echo -e "${YELLOW}📋 MAIN MENU:${NC}"
    echo -e "${GREEN}1.${NC} 🚀 Run Snapshot Now (All Websites)"
    echo -e "${GREEN}2.${NC} 🌐 Manual Snapshot (Any URL)"
    echo -e "${GREEN}3.${NC} 🧪 Test System"
    echo -e "${GREEN}4.${NC} 📊 Check Status"
    echo -e "${GREEN}5.${NC} 📅 Manage Cron Jobs"
    echo -e "${GREEN}6.${NC} 📁 View Snapshots"
    echo -e "${GREEN}7.${NC} 📝 View Logs"
    echo -e "${GREEN}8.${NC} ⚙️  Configuration"
    echo -e "${GREEN}9.${NC} 📚 Help & Documentation"
    echo -e "${GREEN}10.${NC} 🔄 Refresh Status"
    echo -e "${RED}0.${NC} 🚪 Exit"
    echo
    echo -n -e "${BLUE}Enter your choice [0-10]: ${NC}"
}

# Function to run snapshot
run_snapshot() {
    echo -e "${YELLOW}🚀 Running snapshot process...${NC}"
    echo
    $SCRIPT_DIR/website_snapshot.sh
    echo
    echo -e "${GREEN}✅ Snapshot process completed!${NC}"
    read -p "Press Enter to continue..."
}

# Function to run manual snapshot
run_manual_snapshot() {
    while true; do
        clear
        show_header
        echo -e "${YELLOW}🌐 MANUAL SNAPSHOT:${NC}"
        echo "=================="
        echo
        echo -e "${CYAN}Enter a website URL to snapshot:${NC}"
        echo -e "${YELLOW}Examples:${NC}"
        echo "  https://example.com"
        echo "  https://news.google.com"
        echo "  https://github.com"
        echo
        echo -n -e "${BLUE}URL: ${NC}"
        read manual_url
        
        if [ -z "$manual_url" ]; then
            echo -e "${RED}❌ URL cannot be empty!${NC}"
            read -p "Press Enter to continue..."
            continue
        fi
        
        # Validate URL format
        if [[ ! $manual_url =~ ^https?:// ]]; then
            echo -e "${RED}❌ Invalid URL format! Must start with http:// or https://${NC}"
            read -p "Press Enter to continue..."
            continue
        fi
        
        echo
        echo -e "${YELLOW}🔍 Checking if website is accessible...${NC}"
        
        # Check if website is accessible
        local response=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 10 --max-time 30 "$manual_url")
        
        if [ "$response" = "200" ] || [ "$response" = "301" ] || [ "$response" = "302" ] || [ "$response" = "403" ]; then
            echo -e "${GREEN}✅ Website is accessible (HTTP $response)${NC}"
            echo
            echo -e "${YELLOW}📸 Creating snapshot...${NC}"
            
            # Create snapshot using Internet Archive API
            local ia_response=$(curl -s -w "%{http_code}" -o /dev/null \
                -X POST \
                -H "Authorization: LOW XhXHGXAQ91hl3xwd:YQ9r1GOi6ysc9jlD" \
                -H "Content-Type: application/x-www-form-urlencoded" \
                -d "url=$manual_url" \
                "https://web.archive.org/save/")
            
            if [ "$ia_response" = "200" ] || [ "$ia_response" = "201" ]; then
                echo -e "${GREEN}✅ Successfully submitted to Internet Archive (HTTP $ia_response)${NC}"
                echo
                echo -e "${CYAN}🔗 Getting snapshot link...${NC}"
                
                # Get the snapshot link (try to find recent one first)
                local snapshot_url=$(get_most_recent_snapshot "$manual_url")
                
                if [ "$snapshot_url" = "https://web.archive.org/save/$manual_url" ]; then
                    echo -e "${YELLOW}⏳ Snapshot is being processed...${NC}"
                    echo -e "${GREEN}📎 Direct save URL: $snapshot_url${NC}"
                    echo -e "${CYAN}💡 This will show the latest snapshot once processed${NC}"
                else
                    echo -e "${GREEN}📎 Snapshot URL: $snapshot_url${NC}"
                    echo -e "${YELLOW}💡 Tip: Click the link above to view the archived version!${NC}"
                fi
                
                echo
                echo -e "${CYAN}ℹ️  Note: New snapshots may take 5-15 minutes to appear in search results${NC}"
            else
                echo -e "${RED}❌ Failed to submit to Internet Archive (HTTP $ia_response)${NC}"
            fi
        else
            echo -e "${RED}❌ Website is not accessible (HTTP $response)${NC}"
            echo -e "${YELLOW}💡 Try checking the URL or try again later${NC}"
        fi
        
        echo
        echo -e "${CYAN}Options:${NC}"
        echo -e "${GREEN}1.${NC} Snapshot another URL"
        echo -e "${GREEN}2.${NC} View latest snapshot links"
        echo -e "${GREEN}0.${NC} Back to main menu"
        echo
        echo -n -e "${BLUE}Choose option [0-2]: ${NC}"
        read choice
        
        case $choice in
            1)
                continue
                ;;
            2)
                echo
                echo -e "${YELLOW}Latest Snapshot for $manual_url:${NC}"
                local latest_url=$(get_most_recent_snapshot "$manual_url")
                if [ "$latest_url" = "https://web.archive.org/save/$manual_url" ]; then
                    echo -e "${YELLOW}⏳ Checking for recent snapshots...${NC}"
                    echo -e "${GREEN}📎 Direct save URL: $latest_url${NC}"
                    echo -e "${CYAN}💡 This will show the latest snapshot once processed${NC}"
                else
                    echo -e "${GREEN}📎 $latest_url${NC}"
                fi
                echo
                read -p "Press Enter to continue..."
                ;;
            0)
                break
                ;;
            *)
                echo -e "${RED}Invalid choice!${NC}"
                read -p "Press Enter to continue..."
                ;;
        esac
    done
}

# Function to test system
test_system() {
    echo -e "${YELLOW}🧪 Testing system...${NC}"
    echo
    $SCRIPT_DIR/test_snapshot.sh
    echo
    echo -e "${GREEN}✅ System test completed!${NC}"
    read -p "Press Enter to continue..."
}

# Function to check status
check_status() {
    echo -e "${YELLOW}📊 Checking system status...${NC}"
    echo
    $SCRIPT_DIR/check_snapshots.sh
    echo
    read -p "Press Enter to continue..."
}

# Function to manage cron jobs
manage_cron() {
    while true; do
        clear
        show_header
        echo -e "${YELLOW}📅 CRON JOB MANAGEMENT:${NC}"
        echo -e "${GREEN}1.${NC} View Current Cron Jobs"
        echo -e "${GREEN}2.${NC} Schedule Manager (Configure Frequency: 1x, 2x, 3x, 4x+ daily)"
        echo -e "${GREEN}3.${NC} Apply Schedule from Config File (snapshot_config.conf)"
        echo -e "${GREEN}4.${NC} Quick: Enable Daily Snapshot (23:00)"
        echo -e "${GREEN}5.${NC} Quick: Disable All Snapshot Crons"
        echo -e "${GREEN}6.${NC} Test Cron Job (Run Now)"
        echo -e "${GREEN}0.${NC} Back to Main Menu"
        echo
        echo -n -e "${BLUE}Enter your choice [0-6]: ${NC}"
        read cron_choice
        
        case $cron_choice in
            1)
                echo -e "${YELLOW}Current Cron Jobs:${NC}"
                echo "=================="
                crontab -l | grep -E "MAS_ChangeHub|^$" || echo "No archive snapshot cron jobs found"
                echo
                read -p "Press Enter to continue..."
                ;;
            2)
                # Launch schedule manager
                $SCRIPT_DIR/schedule_manager.sh
                ;;
            3)
                # Apply schedule from config file
                echo
                $SCRIPT_DIR/apply_config_schedule.sh
                echo
                read -p "Press Enter to continue..."
                ;;
            4)
                echo -e "${YELLOW}Enabling daily snapshot at 23:00...${NC}"
                (crontab -l 2>/dev/null | grep -v MAS_ChangeHub; echo "0 23 * * * $SCRIPT_DIR/website_snapshot.sh >> $LOG_FILE 2>&1  # Archive snapshot 1x daily") | crontab -
                echo -e "${GREEN}✅ Daily snapshot enabled at 23:00 GMT+2!${NC}"
                read -p "Press Enter to continue..."
                ;;
            5)
                echo -e "${YELLOW}Disabling all snapshot crons...${NC}"
                crontab -l 2>/dev/null | grep -v MAS_ChangeHub | crontab -
                echo -e "${GREEN}✅ All snapshot crons disabled!${NC}"
                read -p "Press Enter to continue..."
                ;;
            6)
                echo -e "${YELLOW}Testing cron job (running snapshot now)...${NC}"
                echo
                $SCRIPT_DIR/website_snapshot.sh
                echo
                echo -e "${GREEN}✅ Test completed!${NC}"
                read -p "Press Enter to continue..."
                ;;
            0)
                break
                ;;
            *)
                echo -e "${RED}Invalid choice!${NC}"
                read -p "Press Enter to continue..."
                ;;
        esac
    done
}

# Function to get latest Wayback Machine URL
get_latest_wayback_url() {
    local url="$1"
    local response=$(curl -s "https://web.archive.org/cdx/search/cdx?url=$url&output=json&limit=1&filter=statuscode:200")
    
    # Extract timestamp from JSON response
    local latest_timestamp=$(echo "$response" | grep -o '"[0-9]\{14\}"' | head -1 | tr -d '"')
    
    if [ -n "$latest_timestamp" ] && [ "$latest_timestamp" != "null" ]; then
        echo "https://web.archive.org/web/$latest_timestamp/$url"
    else
        echo "No snapshot found"
    fi
}

# Function to get the most recent snapshot (including very recent ones)
get_most_recent_snapshot() {
    local url="$1"
    
    # First try to get the latest from the search API
    local latest_url=$(get_latest_wayback_url "$url")
    
    if [ "$latest_url" != "No snapshot found" ]; then
        # Check if this snapshot is from today (within last 24 hours)
        local timestamp=$(echo "$latest_url" | grep -o '[0-9]\{14\}' | head -1)
        if [ -n "$timestamp" ]; then
            local snapshot_date=$(echo "$timestamp" | cut -c1-8)  # YYYYMMDD
            local today=$(date +%Y%m%d)
            
            if [ "$snapshot_date" = "$today" ]; then
                echo "$latest_url"
                return
            fi
        fi
    fi
    
    # If no recent snapshot found, try the direct save URL
    echo "https://web.archive.org/save/$url"
}

# Function to view snapshots
view_snapshots() {
    while true; do
        clear
        show_header
        echo -e "${YELLOW}📁 SNAPSHOT VIEWER:${NC}"
        echo -e "${GREEN}1.${NC} View Latest Wayback Machine Links"
        echo -e "${GREEN}2.${NC} View Snapshot Statistics"
        echo -e "${GREEN}3.${NC} Clean Old Logs"
        echo -e "${GREEN}0.${NC} Back to Main Menu"
        echo
        echo -n -e "${BLUE}Enter your choice [0-3]: ${NC}"
        read snapshot_choice
        
        case $snapshot_choice in
            1)
                echo -e "${YELLOW}Latest Wayback Machine Links:${NC}"
                echo "=============================="
                echo
                
                # List of websites from the snapshot script
                websites=(
                    "https://newstargeted.com"
                    "https://api.newstargeted.com"
                    "https://cmstest.newstargeted.com"
                    "https://console.newstargeted.com"
                    "https://convert.newstargeted.com"
                    "https://dashboard.newstargeted.com"
                    "https://diabetes.newstargeted.com"
                    "https://discord.newstargeted.com"
                    "https://extensions.newstargeted.com"
                    "https://infoskjerm.newstargeted.com"
                    "https://mas.newstargeted.com"
                    "https://rawdata.newstargeted.com"
                    "https://test.newstargeted.com"
                    "https://webhook.newstargeted.com"
                    "https://yourls.newstargeted.com"
                )
                
                echo -e "${CYAN}Fetching latest Wayback Machine links...${NC}"
                echo
                
                for url in "${websites[@]}"; do
                    domain=$(echo "$url" | sed 's|https\?://||' | sed 's|/.*||')
                    echo -n "🔍 $domain: "
                    
                    # Get latest snapshot
                    latest_url=$(get_latest_wayback_url "$url")
                    
                    if [ "$latest_url" != "No snapshot found" ]; then
                        echo -e "${GREEN}✅${NC}"
                        echo "   📎 $latest_url"
                    else
                        echo -e "${RED}❌ No snapshot found${NC}"
                    fi
                    echo
                done
                
                echo -e "${YELLOW}💡 Tip: Click any link above to view the archived version!${NC}"
                read -p "Press Enter to continue..."
                ;;
            2)
                echo -e "${YELLOW}Snapshot Statistics:${NC}"
                echo "===================="
                echo "📊 Internet Archive Snapshots Only"
                echo "🌐 All snapshots are stored on archive.org"
                echo "📅 Daily snapshots at 00:00 GMT+2"
                echo "🔗 Access via Wayback Machine links above"
                echo
                read -p "Press Enter to continue..."
                ;;
            3)
                echo -e "${YELLOW}Cleaning old logs (older than 90 days)...${NC}"
                find $SCRIPT_DIR -name "*.log" -mtime +90 -delete
                echo -e "${GREEN}✅ Old logs cleaned!${NC}"
                read -p "Press Enter to continue..."
                ;;
            0)
                break
                ;;
            *)
                echo -e "${RED}Invalid choice!${NC}"
                read -p "Press Enter to continue..."
                ;;
        esac
    done
}

# Function to view logs
view_logs() {
    while true; do
        clear
        show_header
        echo -e "${YELLOW}📝 LOG VIEWER:${NC}"
        echo -e "${GREEN}1.${NC} View Main Log (last 50 lines)"
        echo -e "${GREEN}2.${NC} Follow Main Log (real-time)"
        echo -e "${GREEN}3.${NC} View Test Log"
        echo -e "${GREEN}4.${NC} Search Logs"
        echo -e "${GREEN}5.${NC} Clear Logs"
        echo -e "${GREEN}0.${NC} Back to Main Menu"
        echo
        echo -n -e "${BLUE}Enter your choice [0-5]: ${NC}"
        read log_choice
        
        case $log_choice in
            1)
                echo -e "${YELLOW}Main Log (last 50 lines):${NC}"
                echo "=========================="
                tail -50 $LOG_FILE
                echo
                read -p "Press Enter to continue..."
                ;;
            2)
                echo -e "${YELLOW}Following main log (Ctrl+C to stop):${NC}"
                echo "====================================="
                tail -f $LOG_FILE
                ;;
            3)
                echo -e "${YELLOW}Test Log:${NC}"
                echo "=========="
                cat $SCRIPT_DIR/test_snapshot.log
                echo
                read -p "Press Enter to continue..."
                ;;
            4)
                echo -n "Enter search term: "
                read search_term
                echo -e "${YELLOW}Search results:${NC}"
                echo "==============="
                grep -i "$search_term" $LOG_FILE
                echo
                read -p "Press Enter to continue..."
                ;;
            5)
                echo -e "${YELLOW}Clearing logs...${NC}"
                > $LOG_FILE
                > $SCRIPT_DIR/test_snapshot.log
                echo -e "${GREEN}✅ Logs cleared!${NC}"
                read -p "Press Enter to continue..."
                ;;
            0)
                break
                ;;
            *)
                echo -e "${RED}Invalid choice!${NC}"
                read -p "Press Enter to continue..."
                ;;
        esac
    done
}

# Function to show configuration
show_config() {
    clear
    show_header
    echo -e "${YELLOW}⚙️  CONFIGURATION:${NC}"
    echo "================"
    echo
    echo -e "${GREEN}Script Directory:${NC} $SCRIPT_DIR"
    echo -e "${GREEN}Log File:${NC} $LOG_FILE"
    echo -e "${GREEN}Config File:${NC} $SCRIPT_DIR/snapshot_config.conf"
    echo
    echo -e "${GREEN}Current Cron Job:${NC}"
    crontab -l | grep MAS_ChangeHub || echo "No cron job configured"
    echo
    echo -e "${GREEN}System Timezone:${NC}"
    timedatectl status | grep "Time zone"
    echo
    echo -e "${GREEN}Websites to Snapshot:${NC}"
    grep -A 20 "WEBSITES=(" $SCRIPT_DIR/website_snapshot.sh | grep "https://" | sed 's/^[[:space:]]*/  /'
    echo
    read -p "Press Enter to continue..."
}

# Function to show help
show_help() {
    clear
    show_header
    echo -e "${YELLOW}📚 HELP & DOCUMENTATION:${NC}"
    echo "========================="
    echo
    echo -e "${GREEN}System Overview:${NC}"
    echo "This system automatically creates daily snapshots of your websites"
    echo "using the Internet Archive's Wayback Machine API."
    echo
    echo -e "${GREEN}Main Features:${NC}"
    echo "• Daily automated snapshots"
    echo "• Internet Archive integration"
    echo "• Local backup creation"
    echo "• Comprehensive logging"
    echo "• Status monitoring"
    echo
    echo -e "${GREEN}Quick Commands:${NC}"
    echo "• Run snapshot: ./website_snapshot.sh"
    echo "• Test system: ./test_snapshot.sh"
    echo "• Check status: ./check_snapshots.sh"
    echo
    echo -e "${GREEN}Documentation:${NC}"
    echo "• README: cat README.md"
    echo "• Config: cat snapshot_config.conf"
    echo
    read -p "Press Enter to continue..."
}

# Function to refresh status
refresh_status() {
    echo -e "${YELLOW}🔄 Refreshing status...${NC}"
    $SCRIPT_DIR/check_snapshots.sh
    echo
    read -p "Press Enter to continue..."
}

# Main menu loop
main() {
    while true; do
        show_header
        show_menu
        read choice
        
        case $choice in
            1) run_snapshot ;;
            2) run_manual_snapshot ;;
            3) test_system ;;
            4) check_status ;;
            5) manage_cron ;;
            6) view_snapshots ;;
            7) view_logs ;;
            8) show_config ;;
            9) show_help ;;
            10) refresh_status ;;
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

# Run main function
main "$@"
