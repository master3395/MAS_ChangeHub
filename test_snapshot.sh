#!/bin/bash

# Test script for website snapshot functionality
# This script tests the snapshot process without creating actual snapshots

SCRIPT_DIR="/home/MAS_ChangeHub"
LOG_FILE="$SCRIPT_DIR/test_snapshot.log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to test website accessibility
test_website() {
    local url="$1"
    local response=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 10 --max-time 30 "$url")
    
    if [ "$response" = "200" ] || [ "$response" = "301" ] || [ "$response" = "302" ] || [ "$response" = "403" ]; then
        log_message "✅ $url - Accessible (HTTP $response)"
        return 0
    else
        log_message "❌ $url - Not accessible (HTTP $response)"
        return 1
    fi
}

# Function to test Internet Archive API
test_ia_api() {
    local url="https://newstargeted.com"
    local access_key="XhXHGXAQ91hl3xwd"
    local secret_key="YQ9r1GOi6ysc9jlD"
    
    log_message "🔍 Testing Internet Archive API with: $url"
    
    local response=$(curl -s -w "%{http_code}" -o /dev/null \
        -X POST \
        -H "Authorization: LOW $access_key:$secret_key" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "url=$url" \
        "https://web.archive.org/save/")
    
    if [ "$response" = "200" ] || [ "$response" = "201" ]; then
        log_message "✅ Internet Archive API test successful (HTTP $response)"
        return 0
    else
        log_message "❌ Internet Archive API test failed (HTTP $response)"
        return 1
    fi
}

# Main test function
main() {
    log_message "🧪 Starting snapshot test process"
    
    # Test websites
    local websites=(
        "https://newstargeted.com"
        "https://api.newstargeted.com"
        "https://mas.newstargeted.com"
        "https://infoskjerm.newstargeted.com"
    )
    
    local accessible_count=0
    local total_count=${#websites[@]}
    
    log_message "📋 Testing website accessibility..."
    for url in "${websites[@]}"; do
        if test_website "$url"; then
            ((accessible_count++))
        fi
        sleep 1
    done
    
    log_message "📊 Accessibility test results: $accessible_count/$total_count websites accessible"
    
    # Test Internet Archive API
    log_message "🔍 Testing Internet Archive API..."
    if test_ia_api; then
        log_message "✅ Internet Archive API is working"
    else
        log_message "❌ Internet Archive API test failed"
    fi
    
    # Test backup directory creation
    log_message "📁 Testing backup directory creation..."
    local test_backup_dir="$SCRIPT_DIR/test_backups"
    if mkdir -p "$test_backup_dir"; then
        log_message "✅ Backup directory creation successful"
        rmdir "$test_backup_dir"
    else
        log_message "❌ Backup directory creation failed"
    fi
    
    log_message "🏁 Test process completed"
}

# Run main function
main "$@"
