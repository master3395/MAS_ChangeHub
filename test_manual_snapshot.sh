#!/bin/bash

# Test script for manual snapshot functionality

echo "🌐 Testing Manual Snapshot Feature"
echo "=================================="
echo

# Test URL
test_url="https://example.com"

echo "Testing with URL: $test_url"
echo

# Check if website is accessible
echo "🔍 Checking if website is accessible..."
response=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 10 --max-time 30 "$test_url")

if [ "$response" = "200" ] || [ "$response" = "301" ] || [ "$response" = "302" ] || [ "$response" = "403" ]; then
    echo "✅ Website is accessible (HTTP $response)"
    echo
    echo "📸 Creating snapshot..."
    
    # Create snapshot using Internet Archive API
    ia_response=$(curl -s -w "%{http_code}" -o /dev/null \
        -X POST \
        -H "Authorization: LOW XhXHGXAQ91hl3xwd:YQ9r1GOi6ysc9jlD" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "url=$test_url" \
        "https://web.archive.org/save/")
    
    if [ "$ia_response" = "200" ] || [ "$ia_response" = "201" ]; then
        echo "✅ Successfully submitted to Internet Archive (HTTP $ia_response)"
        echo
        echo "🔗 Snapshot submitted! Check archive.org in a few minutes."
        echo "💡 You can also use the CLI menu to view snapshot links."
    else
        echo "❌ Failed to submit to Internet Archive (HTTP $ia_response)"
    fi
else
    echo "❌ Website is not accessible (HTTP $response)"
fi

echo
echo "✅ Manual snapshot test completed!"
