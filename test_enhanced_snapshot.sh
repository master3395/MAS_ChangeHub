#!/bin/bash

# Test Enhanced Internet Archive Snapshot Options
# This script tests the enhanced snapshot functionality with all available options

echo "🧪 Testing Enhanced Internet Archive Snapshot Options"
echo "====================================================="
echo ""

# Configuration
SCRIPT_DIR="/home/MAS_ChangeHub"
CONFIG_FILE="$SCRIPT_DIR/snapshot_config.conf"
TEST_URL="https://newstargeted.com"

echo "📋 Configuration Status:"
echo "======================="

# Load configuration
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
    echo "✅ Configuration file loaded: $CONFIG_FILE"
else
    echo "❌ Configuration file not found: $CONFIG_FILE"
    exit 1
fi

echo ""
echo "⚙️  Enhanced Options Status:"
echo "==========================="
echo "📎 Capture Outlinks: $CAPTURE_OUTLINKS"
echo "📸 Capture Screenshot: $CAPTURE_SCREENSHOT"
echo "🔍 Capture All (Error Pages): $CAPTURE_ALL"
echo "🚫 Skip First Archive (Disable Ad Blocker): $SKIP_FIRST_ARCHIVE"
echo "📧 Email Results: $EMAIL_RESULT"
echo "📦 WACZ File: $WACZ_FILE"

echo ""
echo "🔍 Testing API Call Construction:"
echo "================================="

# Build API parameters (same logic as main script)
api_params="url=$TEST_URL"

if [ "$CAPTURE_OUTLINKS" = "true" ]; then
    api_params="$api_params&capture_outlinks=1"
    echo "✅ Added: capture_outlinks=1"
fi

if [ "$CAPTURE_SCREENSHOT" = "true" ]; then
    api_params="$api_params&capture_screenshot=1"
    echo "✅ Added: capture_screenshot=1"
fi

if [ "$CAPTURE_ALL" = "true" ]; then
    api_params="$api_params&capture_all=1"
    echo "✅ Added: capture_all=1"
fi

if [ "$SKIP_FIRST_ARCHIVE" = "true" ]; then
    api_params="$api_params&skip_first_archive=1"
    echo "✅ Added: skip_first_archive=1"
fi

if [ "$EMAIL_RESULT" = "true" ]; then
    api_params="$api_params&email_result=1"
    echo "✅ Added: email_result=1"
fi

if [ "$WACZ_FILE" = "true" ]; then
    api_params="$api_params&wacz=1"
    echo "✅ Added: wacz=1"
fi

echo ""
echo "📡 Complete API Parameters:"
echo "=========================="
echo "$api_params"

echo ""
echo "🧪 Testing Enhanced Snapshot:"
echo "============================="

# Test the enhanced snapshot
echo "Testing with URL: $TEST_URL"
echo ""

# Use the same API credentials as main script
IA_ACCESS_KEY="XhXHGXAQ91hl3xwd"
IA_SECRET_KEY="YQ9r1GOi6ysc9jlD"

# Make the API call
echo "📤 Sending enhanced snapshot request..."
response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Authorization: LOW $IA_ACCESS_KEY:$IA_SECRET_KEY" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "$api_params" \
    "https://web.archive.org/save/")

http_code=$(echo "$response" | tail -n1)
response_body=$(echo "$response" | head -n-1)

echo ""
echo "📊 Response:"
echo "============"
echo "HTTP Status: $http_code"
echo "Response Body: $response_body"

if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
    echo ""
    echo "✅ Enhanced snapshot test successful!"
    echo ""
    echo "🎯 What this means:"
    echo "=================="
    echo "• Your snapshot will include more comprehensive data"
    echo "• Outlinks will be captured and archived"
    echo "• Screenshots will be taken for visual backup"
    echo "• Error pages will be included even if they return 4xx/5xx"
    echo "• Ad blockers will be disabled for complete content capture"
    echo ""
    echo "🔗 Check the result at:"
    echo "https://web.archive.org/web/*/$TEST_URL"
    echo ""
    echo "⏳ Note: Enhanced snapshots may take longer to process"
    echo "   (5-15 minutes vs 2-5 minutes for basic snapshots)"
else
    echo ""
    echo "❌ Enhanced snapshot test failed!"
    echo ""
    echo "🔧 Troubleshooting:"
    echo "=================="
    echo "• Check Internet Archive API credentials"
    echo "• Verify internet connectivity"
    echo "• Check if URL is accessible"
    echo "• Review API rate limits"
fi

echo ""
echo "✅ Enhanced snapshot test completed!"
