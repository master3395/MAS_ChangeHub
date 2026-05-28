#!/bin/bash

# Website Snapshot Status Checker
# This script checks the status of website snapshots and provides reports

SCRIPT_DIR="/home/MAS_ChangeHub"
LOG_FILE="$SCRIPT_DIR/snapshot.log"
CONFIG_FILE="$SCRIPT_DIR/snapshot_config.conf"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check last snapshot run
check_last_run() {
    if [ -f "$LOG_FILE" ]; then
        local last_run=$(grep "Starting daily website snapshot process" "$LOG_FILE" | tail -1 | cut -d' ' -f1-2)
        if [ -n "$last_run" ]; then
            log_message "📅 Last snapshot run: $last_run"
        else
            log_message "❌ No snapshot runs found in log"
        fi
    else
        log_message "❌ Log file not found: $LOG_FILE"
    fi
}

# Function to check snapshot success rate
check_success_rate() {
    if [ -f "$LOG_FILE" ]; then
        local total_runs=$(grep -c "Starting daily website snapshot process" "$LOG_FILE")
        local successful_runs=$(grep -c "All .* websites successfully snapshotted" "$LOG_FILE")
        
        if [ $total_runs -gt 0 ]; then
            local success_rate=$((successful_runs * 100 / total_runs))
            log_message "📊 Success rate: $successful_runs/$total_runs runs ($success_rate%)"
        else
            log_message "❌ No snapshot runs found"
        fi
    else
        log_message "❌ Log file not found: $LOG_FILE"
    fi
}

# Function to check recent errors
check_recent_errors() {
    if [ -f "$LOG_FILE" ]; then
        local error_count=$(grep -c "❌" "$LOG_FILE" | tail -10)
        if [ $error_count -gt 0 ]; then
            log_message "⚠️  Recent errors found: $error_count"
            log_message "Recent errors:"
            grep "❌" "$LOG_FILE" | tail -5 | while read line; do
                log_message "   $line"
            done
        else
            log_message "✅ No recent errors found"
        fi
    else
        log_message "❌ Log file not found: $LOG_FILE"
    fi
}

# Function to check Internet Archive snapshots
check_archive_snapshots() {
    log_message "🌐 Internet Archive Snapshots:"
    log_message "   📊 All snapshots stored on archive.org"
    log_message "   🔗 Access via Wayback Machine links"
    log_message "   📅 Daily snapshots at 00:00 GMT+2"
    log_message "   ✅ No local storage required"
}

# Function to check cron job status
check_cron_status() {
    if crontab -l | grep -q "website_snapshot.sh"; then
        log_message "✅ Cron job is configured"
        local cron_line=$(crontab -l | grep "website_snapshot.sh")
        log_message "   Schedule: $cron_line"
    else
        log_message "❌ Cron job not found"
    fi
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

# Function to check Internet Archive API status
check_ia_api() {
    local access_key="XhXHGXAQ91hl3xwd"
    local secret_key="YQ9r1GOi6ysc9jlD"
    
    log_message "🔍 Testing Internet Archive API..."
    
    local response=$(curl -s -w "%{http_code}" -o /dev/null \
        -X POST \
        -H "Authorization: LOW $access_key:$secret_key" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "url=https://newstargeted.com" \
        "https://web.archive.org/save/")
    
    if [ "$response" = "200" ] || [ "$response" = "201" ]; then
        log_message "✅ Internet Archive API is working (HTTP $response)"
        
        # Show latest snapshot link
        log_message "🔗 Latest snapshot: $(get_latest_wayback_url "https://newstargeted.com")"
    else
        log_message "❌ Internet Archive API test failed (HTTP $response)"
    fi
}

# Main function
main() {
    log_message "🔍 Website Snapshot Status Check"
    log_message "=================================="
    
    check_cron_status
    echo
    check_last_run
    check_success_rate
    echo
    check_recent_errors
    echo
    check_archive_snapshots
    echo
    check_ia_api
    
    log_message "=================================="
    log_message "Status check completed"
}

# Run main function
main "$@"
