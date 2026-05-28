#!/bin/bash

# Test script for Wayback Machine links functionality

echo "🔗 Testing Wayback Machine Links Feature"
echo "========================================"
echo

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

# Test a few websites
websites=(
    "https://newstargeted.com"
    "https://api.newstargeted.com"
    "https://mas.newstargeted.com"
)

echo "Fetching latest Wayback Machine links..."
echo

for url in "${websites[@]}"; do
    domain=$(echo "$url" | sed 's|https\?://||' | sed 's|/.*||')
    echo -n "🔍 $domain: "
    
    # Get latest snapshot
    latest_url=$(get_latest_wayback_url "$url")
    
    if [ "$latest_url" != "No snapshot found" ]; then
        echo "✅"
        echo "   📎 $latest_url"
    else
        echo "❌ No snapshot found"
    fi
    echo
done

echo "💡 Tip: Click any link above to view the archived version!"
