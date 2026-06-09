#!/bin/bash

# Test script for website snapshot functionality
# This script tests the snapshot process without creating actual snapshots


MAS_CHANGEHUB_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/project_paths.sh
source "$MAS_CHANGEHUB_ROOT/lib/project_paths.sh"
LOG_FILE="$MAS_TEST_SNAPSHOT_LOG"
CONFIG_FILE="$MAS_SNAPSHOT_CONFIG"
if [ -f "$CONFIG_FILE" ]; then
    # shellcheck source=/dev/null
    source "$CONFIG_FILE"
else
    echo "Configuration file not found: $CONFIG_FILE" >&2
    exit 1
fi

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

# Function to test Internet Archive API (status endpoints; optional save POST)
test_ia_api() {
    if [ -z "$IA_ACCESS_KEY" ] || [ -z "$IA_SECRET_KEY" ]; then
        log_message "❌ IA_ACCESS_KEY and IA_SECRET_KEY must be set in $CONFIG_FILE"
        return 1
    fi

    local cache_bust http_code
    cache_bust=$(date +%s)
    log_message "🔍 Testing Internet Archive user status (read-only)"

    http_code=$(curl -s -o /dev/null -w "%{http_code}" \
        --connect-timeout 10 --max-time 30 \
        -H "Authorization: LOW ${IA_ACCESS_KEY}:${IA_SECRET_KEY}" \
        -H "Accept: application/json" \
        "https://web.archive.org/save/status/user?_t=${cache_bust}")

    if [ "$http_code" != "200" ]; then
        log_message "❌ Internet Archive user status failed (HTTP $http_code)"
        return 1
    fi
    log_message "✅ Internet Archive user status OK (HTTP $http_code)"

    if [ "${TEST_IA_SAVE_SUBMIT:-false}" != "true" ]; then
        log_message "ℹ️  Skipping Save POST (set TEST_IA_SAVE_SUBMIT=true to test /save/)"
        return 0
    fi

    local url="${MAIN_DOMAIN:-https://newstargeted.com}"
    log_message "🔍 Testing Save Page Now POST with if_not_archived_within (one request)"
    http_code=$(curl -s -w "%{http_code}" -o /dev/null \
        --connect-timeout 30 --max-time 60 \
        -X POST \
        -H "Authorization: LOW ${IA_ACCESS_KEY}:${IA_SECRET_KEY}" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -H "Accept: application/json" \
        -d "url=${url}&if_not_archived_within=${IF_NOT_ARCHIVED_WITHIN:-20h}&skip_first_archive=1" \
        "https://web.archive.org/save/")

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        log_message "✅ Internet Archive Save POST successful (HTTP $http_code)"
        return 0
    fi
    log_message "❌ Internet Archive Save POST failed (HTTP $http_code)"
    return 1
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
    local test_backup_dir="$MAS_TEST_DIR/test_backups"
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
