#!/bin/bash

# Test Discord Webhook for Archive Snapshots
# This script sends a test notification to verify the webhook is working

echo "Testing Discord Webhook Integration..."
echo "======================================"
echo ""

# Discord webhook URL
DISCORD_WEBHOOK_URL="https://discord.com/api/webhooks/1425273824701579386/EUCQQ6ix__lPp8qWpRr1_bL05G2CRWGqcw_MVdbIxSROYkJcbTS0iTXkeamghwwZhuRf"

if [ -z "$DISCORD_WEBHOOK_URL" ]; then
    echo "❌ Discord webhook URL is not set"
    exit 1
fi

echo "✅ Discord webhook is configured"
echo "📡 Webhook URL: ${DISCORD_WEBHOOK_URL:0:50}..."
echo ""

# Build test notification
timestamp=$(date -u '+%Y-%m-%dT%H:%M:%S.000Z')
timestamp_display=$(date '+%Y-%m-%d %H:%M:%S %Z')

payload=$(cat <<EOF
{
  "embeds": [{
    "title": "🧪 Test Notification - Internet Archive Snapshot Manager",
    "description": "This is a test notification sent at **$timestamp_display**",
    "color": 3447003,
    "fields": [
      {
        "name": "📊 Test Statistics",
        "value": "**Total Websites:** 15\n**Successful Snapshots:** 15\n**Failed Snapshots:** 0",
        "inline": false
      },
      {
        "name": "🌐 Websites Archived",
        "value": "• [newstargeted.com](https://web.archive.org/web/*/https://newstargeted.com/) - Main domain\n• [api.newstargeted.com](https://web.archive.org/web/*/https://api.newstargeted.com/)\n• [infoskjerm.newstargeted.com](https://web.archive.org/web/*/https://infoskjerm.newstargeted.com/)\n• [mas.newstargeted.com](https://web.archive.org/web/*/https://mas.newstargeted.com/)\n• [discord.newstargeted.com](https://web.archive.org/web/*/https://discord.newstargeted.com/)\n• and 10 more domains...",
        "inline": false
      },
      {
        "name": "📦 View All Snapshots",
        "value": "Click any domain link above to view its Wayback Machine calendar with all historical snapshots.",
        "inline": false
      },
      {
        "name": "✅ Status",
        "value": "Webhook integration is working correctly!",
        "inline": false
      }
    ],
    "footer": {
      "text": "Internet Archive Snapshot Manager v1.0 • newstargeted.com"
    },
    "timestamp": "$timestamp"
  }]
}
EOF
)

echo "Sending test notification..."

# Send webhook
response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "$payload" \
    "$DISCORD_WEBHOOK_URL")

http_code=$(echo "$response" | tail -n1)

echo ""

if [ "$http_code" = "204" ] || [ "$http_code" = "200" ]; then
    echo "✅ Test notification sent successfully!"
    echo "   Check your Discord channel for the test message."
else
    echo "❌ Failed to send notification: HTTP $http_code"
    echo "Response: $(echo "$response" | head -n-1)"
    exit 1
fi

echo ""
echo "Done!"

