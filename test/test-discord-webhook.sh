#!/bin/bash


echo "Testing Discord Webhook Integration..."
echo "======================================"
echo ""
MAS_CHANGEHUB_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/project_paths.sh
source "$MAS_CHANGEHUB_ROOT/lib/project_paths.sh"
CONFIG_FILE="$MAS_SNAPSHOT_CONFIG"

if [ -f "$CONFIG_FILE" ]; then
    # shellcheck source=/dev/null
    source "$CONFIG_FILE"
fi

DISCORD_USE_CV2="${DISCORD_USE_CV2:-true}"
DISCORD_HERO_IMAGE_URL="${DISCORD_HERO_IMAGE_URL:-https://newstargeted.com/assets/status-cv2/archive.png}"

if [ "$DISCORD_WEBHOOK_ENABLED" != "true" ] || [ -z "$DISCORD_WEBHOOK_URL" ]; then
    echo "Discord webhook is disabled or URL not set in snapshot_config.conf"
    exit 1
fi

echo "Discord webhook is configured"
echo "Sending CV2 test notification..."

payload_file=$(mktemp /tmp/mas-discord-test-XXXXXX.json)
python3 -c '
import json, sys
payload = {
    "webhook_url": sys.argv[1],
    "use_cv2": True,
    "success_count": 15,
    "total_count": 15,
    "failed_count": 0,
    "skipped_count": 0,
    "failed_details": "",
    "websites_list": "• [newstargeted.com](https://web.archive.org/web/*/https://newstargeted.com/)\n• [api.newstargeted.com](https://web.archive.org/web/*/https://api.newstargeted.com/)",
    "capture_options": "• Simple capture (URL only)\n",
    "timezone": "Europe/Oslo",
    "hero_image_url": sys.argv[2],
    "username": "Archive Snapshot Manager",
}
with open(sys.argv[3], "w", encoding="utf-8") as handle:
    json.dump(payload, handle)
' "$DISCORD_WEBHOOK_URL" "$DISCORD_HERO_IMAGE_URL" "$payload_file"

if php "$MAS_CHANGEHUB_ROOT/lib/discord_cv2_send.php" < "$payload_file"; then
    echo "Test notification sent successfully."
    echo "Check your Discord channel for the CV2 message."
else
    echo "Failed to send test notification."
    rm -f "$payload_file"
    exit 1
fi

rm -f "$payload_file"
echo "Done!"
