#!/bin/bash

# Website Snapshot Script for newstargeted.com
# Creates snapshots using Internet Archive Wayback Machine Save API

export TZ='Europe/Oslo'

SCRIPT_DIR="/home/MAS_ChangeHub"
LOG_FILE="$SCRIPT_DIR/snapshot.log"
CONFIG_FILE="$SCRIPT_DIR/snapshot_config.conf"
STATE_DIR="$SCRIPT_DIR/state"
LAST_SUCCESS_FILE="$STATE_DIR/last_success.txt"
RATE_LIMIT_FILE="$STATE_DIR/rate_limit_until.txt"

mkdir -p "$STATE_DIR"

if [ -f "$CONFIG_FILE" ]; then
    # shellcheck source=/dev/null
    source "$CONFIG_FILE"
else
    echo "Configuration file not found: $CONFIG_FILE" >&2
    exit 1
fi

CAPTURE_OUTLINKS="${CAPTURE_OUTLINKS:-false}"
CAPTURE_SCREENSHOT="${CAPTURE_SCREENSHOT:-false}"
CAPTURE_ALL="${CAPTURE_ALL:-false}"
EMAIL_RESULT="${EMAIL_RESULT:-false}"
WACZ_FILE="${WACZ_FILE:-false}"
DOMAIN_SELECTION_MODE="${DOMAIN_SELECTION_MODE:-main}"
MAIN_DOMAIN="${MAIN_DOMAIN:-https://newstargeted.com}"
DISCORD_WEBHOOK_ENABLED="${DISCORD_WEBHOOK_ENABLED:-true}"
DISCORD_NOTIFY_ON_FAILURE_ONLY="${DISCORD_NOTIFY_ON_FAILURE_ONLY:-true}"
DISCORD_NOTIFY_ON_SUCCESS="${DISCORD_NOTIFY_ON_SUCCESS:-false}"
DISCORD_USE_CV2="${DISCORD_USE_CV2:-true}"
DISCORD_HERO_IMAGE_URL="${DISCORD_HERO_IMAGE_URL:-https://newstargeted.com/assets/status-cv2/archive.png}"
REQUEST_TIMEOUT="${REQUEST_TIMEOUT:-60}"
CONNECT_TIMEOUT="${CONNECT_TIMEOUT:-30}"
DELAY_BETWEEN_REQUESTS="${DELAY_BETWEEN_REQUESTS:-20}"
MAX_RETRIES="${MAX_RETRIES:-1}"
MAX_RETRIES_ON_RATE_LIMIT="${MAX_RETRIES_ON_RATE_LIMIT:-0}"
RETRY_BASE_DELAY_SECONDS="${RETRY_BASE_DELAY_SECONDS:-120}"
RATE_LIMIT_COOLDOWN_HOURS="${RATE_LIMIT_COOLDOWN_HOURS:-24}"
SKIP_IF_SNAPSHOT_WITHIN_HOURS="${SKIP_IF_SNAPSHOT_WITHIN_HOURS:-20}"
IF_NOT_ARCHIVED_WITHIN="${IF_NOT_ARCHIVED_WITHIN:-20h}"
FALLBACK_SIMPLE_ON_RATE_LIMIT="${FALLBACK_SIMPLE_ON_RATE_LIMIT:-false}"
PREFLIGHT_IA_STATUS="${PREFLIGHT_IA_STATUS:-true}"
CDX_PRECHECK_ENABLED="${CDX_PRECHECK_ENABLED:-true}"
DELAY_WB_AVAILABILITY="${DELAY_WB_AVAILABILITY:-false}"
SKIP_FIRST_ARCHIVE="${SKIP_FIRST_ARCHIVE:-true}"
IA_API_URL="${IA_API_URL:-https://web.archive.org/save/}"
IA_STATUS_SYSTEM_URL="${IA_STATUS_SYSTEM_URL:-https://web.archive.org/save/status/system}"
IA_STATUS_USER_URL="${IA_STATUS_USER_URL:-https://web.archive.org/save/status/user}"
RUN_HIT_RATE_LIMIT=0

if [ -z "$IA_ACCESS_KEY" ] || [ -z "$IA_SECRET_KEY" ]; then
    echo "IA_ACCESS_KEY and IA_SECRET_KEY must be set in $CONFIG_FILE" >&2
    exit 1
fi

SNAPSHOT_URLS=()
case "$DOMAIN_SELECTION_MODE" in
    "main")
        SNAPSHOT_URLS=("$MAIN_DOMAIN")
        ;;
    "custom")
        if [ ${#CUSTOM_DOMAINS[@]} -gt 0 ]; then
            SNAPSHOT_URLS=("${CUSTOM_DOMAINS[@]}")
        else
            SNAPSHOT_URLS=("${WEBSITES[@]}")
        fi
        ;;
    "all"|*)
        SNAPSHOT_URLS=("${WEBSITES[@]}")
        ;;
esac

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
    if [ "${LOG_TO_STDOUT:-false}" = "true" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    fi
}

is_rate_limited_cooldown() {
    if [ ! -f "$RATE_LIMIT_FILE" ]; then
        return 1
    fi
    local until_ts
    until_ts=$(cat "$RATE_LIMIT_FILE" 2>/dev/null)
    if [ -z "$until_ts" ]; then
        return 1
    fi
    local now_ts
    now_ts=$(date +%s)
    if [ "$now_ts" -lt "$until_ts" ]; then
        return 0
    fi
    rm -f "$RATE_LIMIT_FILE"
    return 1
}

set_rate_limit_cooldown() {
    local hours="${1:-$RATE_LIMIT_COOLDOWN_HOURS}"
    local until_ts
    until_ts=$(( $(date +%s) + (hours * 3600) ))
    echo "$until_ts" > "$RATE_LIMIT_FILE"
    log_message "Rate limit cooldown active until $(date -d "@$until_ts" '+%Y-%m-%d %H:%M:%S %Z') ($hours h)"
}

recent_snapshot_recorded() {
    local url="$1"
    if [ ! -f "$LAST_SUCCESS_FILE" ]; then
        return 1
    fi
    local line success_ts now_ts age_hours
    line=$(grep -F "|$url|" "$LAST_SUCCESS_FILE" 2>/dev/null | tail -n1)
    if [ -z "$line" ]; then
        return 1
    fi
    success_ts=$(echo "$line" | cut -d'|' -f1)
    now_ts=$(date +%s)
    age_hours=$(( (now_ts - success_ts) / 3600 ))
    if [ "$age_hours" -lt "$SKIP_IF_SNAPSHOT_WITHIN_HOURS" ]; then
        return 0
    fi
    return 1
}

record_success() {
    local url="$1"
    local mode="$2"
    local ts
    ts=$(date +%s)
    grep -vF "|$url|" "$LAST_SUCCESS_FILE" 2>/dev/null > "${LAST_SUCCESS_FILE}.tmp" || true
    echo "${ts}|${url}|${mode}" >> "${LAST_SUCCESS_FILE}.tmp"
    mv "${LAST_SUCCESS_FILE}.tmp" "$LAST_SUCCESS_FILE"
}

# shellcheck source=lib/snapshot_ia_helpers.sh
source "$SCRIPT_DIR/lib/snapshot_ia_helpers.sh"

create_snapshot() {
    local url="$1"
    local -n failure_reason_ref=$2
    local attempt=1
    local max_attempts="$MAX_RETRIES"

    log_message "Creating snapshot for: $url"

    if [ "$RUN_HIT_RATE_LIMIT" -eq 1 ]; then
        failure_reason_ref="Skipped: rate limit hit earlier in this run"
        log_message "Skipping $url: $failure_reason_ref"
        return 0
    fi

    if is_rate_limited_cooldown; then
        failure_reason_ref="Skipped: Internet Archive rate limit cooldown (avoiding HTTP 429)"
        log_message "Skipping $url: $failure_reason_ref"
        return 0
    fi

    if recent_snapshot_recorded "$url"; then
        failure_reason_ref="Skipped: successful snapshot within last ${SKIP_IF_SNAPSHOT_WITHIN_HOURS}h (local state)"
        log_message "Skipping $url ($failure_reason_ref)"
        return 0
    fi

    if [ "$CDX_PRECHECK_ENABLED" = "true" ] && wayback_recent_capture_exists "$url"; then
        failure_reason_ref="Skipped: recent Wayback capture within last ${SKIP_IF_SNAPSHOT_WITHIN_HOURS}h (CDX)"
        log_message "Skipping $url ($failure_reason_ref)"
        return 0
    fi

    log_capture_options
    log_message "   Submitting one Save Page Now request (no 429 retries)"

    while [ "$attempt" -le "$max_attempts" ]; do
        local result http_code body_snippet spn_error
        result=$(submit_to_archive "$url")
        http_code="${result%%|*}"
        body_snippet="${result#*|}"

        if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
            log_message "Successfully submitted $url (HTTP $http_code)"
            record_success "$url" "spn2"
            rm -f "$RATE_LIMIT_FILE"
            failure_reason_ref=""
            return 0
        fi

        if is_spn_rate_limit_response "$http_code" "$body_snippet"; then
            spn_error=$(extract_spn_error_code "$body_snippet")
            handle_rate_limit_hit "$url" "$http_code" "$spn_error"
            failure_reason_ref="Internet Archive rate limited (HTTP $http_code). Too many Save-Page-Now requests."
            return 1
        fi

        if [ "$attempt" -lt "$max_attempts" ]; then
            log_message "   Non-rate-limit error HTTP $http_code; retry $attempt/$max_attempts in ${RETRY_BASE_DELAY_SECONDS}s"
            sleep "$RETRY_BASE_DELAY_SECONDS"
            attempt=$((attempt + 1))
            continue
        fi

        failure_reason_ref="Internet Archive error (HTTP $http_code)"
        log_message "Failed to submit $url (HTTP $http_code)"
        return 1
    done

    return 1
}

check_website() {
    local url="$1"
    local attempt=1
    local response=""
    while [ "$attempt" -le 2 ]; do
        response=$(curl -s -o /dev/null -w "%{http_code}" -L --connect-timeout "$CONNECT_TIMEOUT" --max-time "$REQUEST_TIMEOUT" "$url")
        if [ "$response" = "200" ] || [ "$response" = "301" ] || [ "$response" = "302" ] || [ "$response" = "403" ]; then
            return 0
        fi
        if [ "$attempt" -eq 1 ]; then
            log_message "   Accessibility check returned HTTP ${response:-timeout}, retrying once..."
            sleep 3
        fi
        attempt=$((attempt + 1))
    done
    return 1
}


build_websites_list_for_discord() {
    local list=""
    local url host
    for url in "${SNAPSHOT_URLS[@]}"; do
        host=$(echo "$url" | sed -e 's|^https\?://||' -e 's|/.*$||')
        if [ -z "$list" ]; then
            list="• [${host}](https://web.archive.org/web/*/${url}/)"
        else
            list="${list}\n• [${host}](https://web.archive.org/web/*/${url}/)"
        fi
    done
    echo "$list"
}

send_discord_notification() {
    local success_count=$1
    local total_count=$2
    local failed_count=$3
    local failed_details_json="$4"
    local skipped_count="${5:-0}"

    if [ "$DISCORD_WEBHOOK_ENABLED" != "true" ] || [ -z "$DISCORD_WEBHOOK_URL" ]; then
        log_message "Discord webhook not enabled or URL not set"
        return
    fi

    if [ "$failed_count" -eq 0 ] && [ "$DISCORD_NOTIFY_ON_FAILURE_ONLY" = "true" ]; then
        if [ "$DISCORD_NOTIFY_ON_SUCCESS" != "true" ]; then
            log_message "Discord notification skipped (run succeeded; notify-on-failure-only)"
            return
        fi
    fi

    if [ "$failed_count" -eq 0 ] && [ "$DISCORD_NOTIFY_ON_SUCCESS" != "true" ]; then
        log_message "Discord notification skipped (success notifications disabled)"
        return
    fi

    local websites_list
    websites_list=$(build_websites_list_for_discord)

    local options_info=""
    if [ "$CAPTURE_OUTLINKS" = "true" ]; then
        options_info="${options_info}• Outlinks captured"$'\n'
    fi
    if [ "$CAPTURE_SCREENSHOT" = "true" ]; then
        options_info="${options_info}• Screenshots taken"$'\n'
    fi
    if [ "$CAPTURE_ALL" = "true" ]; then
        options_info="${options_info}• Error pages included"$'\n'
    fi
    if [ "$SKIP_FIRST_ARCHIVE" = "true" ]; then
        options_info="${options_info}• skip_first_archive enabled"$'\n'
    fi
    if [ -z "$options_info" ]; then
        options_info="• Simple capture (URL only) to reduce Internet Archive rate limits"$'\n'
    fi

    local payload_file
    payload_file=$(mktemp /tmp/mas-discord-XXXXXX.json)
    export MAS_CV2_WEBHOOK_URL="$DISCORD_WEBHOOK_URL"
    export MAS_CV2_USE_CV2="$DISCORD_USE_CV2"
    export MAS_CV2_SUCCESS_COUNT="$success_count"
    export MAS_CV2_TOTAL_COUNT="$total_count"
    export MAS_CV2_FAILED_COUNT="$failed_count"
    export MAS_CV2_SKIPPED_COUNT="$skipped_count"
    export MAS_CV2_FAILED_DETAILS="$failed_details_json"
    export MAS_CV2_WEBSITES_LIST="$websites_list"
    export MAS_CV2_CAPTURE_OPTIONS="$options_info"
    export MAS_CV2_HERO_IMAGE_URL="$DISCORD_HERO_IMAGE_URL"

    python3 -c '
import json, os, subprocess, sys
payload = {
    "webhook_url": os.environ.get("MAS_CV2_WEBHOOK_URL", ""),
    "use_cv2": os.environ.get("MAS_CV2_USE_CV2", "true") == "true",
    "success_count": int(os.environ.get("MAS_CV2_SUCCESS_COUNT", "0") or 0),
    "total_count": int(os.environ.get("MAS_CV2_TOTAL_COUNT", "0") or 0),
    "failed_count": int(os.environ.get("MAS_CV2_FAILED_COUNT", "0") or 0),
    "skipped_count": int(os.environ.get("MAS_CV2_SKIPPED_COUNT", "0") or 0),
    "failed_details": os.environ.get("MAS_CV2_FAILED_DETAILS", ""),
    "websites_list": os.environ.get("MAS_CV2_WEBSITES_LIST", ""),
    "capture_options": os.environ.get("MAS_CV2_CAPTURE_OPTIONS", ""),
    "timezone": "Europe/Oslo",
    "hero_image_url": os.environ.get("MAS_CV2_HERO_IMAGE_URL", ""),
    "username": "Archive Snapshot Manager",
}
path = sys.argv[1]
with open(path, "w", encoding="utf-8") as handle:
    json.dump(payload, handle)
' "$payload_file"

    log_message "Sending Discord notification (CV2)..."
    if ! php "$SCRIPT_DIR/lib/discord_cv2_send.php" < "$payload_file"; then
        rm -f "$payload_file"
        log_message "Failed to send Discord notification"
        return 1
    fi
    rm -f "$payload_file"
    log_message "Discord notification sent successfully"
}

main() {
    log_message "Starting daily website snapshot process"
    log_message "Date: $(date '+%Y-%m-%d %H:%M:%S %Z')"
    log_message "Domain mode: $DOMAIN_SELECTION_MODE (${#SNAPSHOT_URLS[@]} domains)"
    log_message "Rate-limit policy: max ${MAX_RETRIES} POST per URL, no retry on 429/503, cooldown ${RATE_LIMIT_COOLDOWN_HOURS}h"

    if is_rate_limited_cooldown; then
        log_message "Run skipped: Internet Archive rate limit cooldown still active"
        log_message "Daily snapshot process completed (cooldown)"
        exit 0
    fi

    if ! preflight_archive_service; then
        log_message "Daily snapshot process completed (preflight skip)"
        exit 0
    fi

    local success_count=0
    local skipped_count=0
    local total_count=${#SNAPSHOT_URLS[@]}
    local failed_urls=()
    local failed_reasons=()

    for url in "${SNAPSHOT_URLS[@]}"; do
        log_message "Processing: $url"

        if ! check_website "$url"; then
            log_message "Website is not accessible: $url"
            failed_urls+=("$url")
            failed_reasons+=("Site not reachable (HTTP check failed)")
            continue
        fi

        log_message "Website is accessible: $url"

        local reason=""
        if create_snapshot "$url" reason; then
            if [ -n "$reason" ] && [[ "$reason" == Skipped:* ]]; then
                skipped_count=$((skipped_count + 1))
            else
                success_count=$((success_count + 1))
            fi
        else
            failed_urls+=("$url")
            failed_reasons+=("${reason:-Unknown error}")
            if [ "$RUN_HIT_RATE_LIMIT" -eq 1 ]; then
                log_message "Aborting remaining URLs after rate limit (fail-safe)"
                break
            fi
        fi

        sleep "$DELAY_BETWEEN_REQUESTS"
    done

    local failed_count=${#failed_urls[@]}
    log_message "Snapshot Summary:"
    log_message "   Total websites: $total_count"
    log_message "   Successful snapshots: $success_count"
    log_message "   Skipped: $skipped_count"
    log_message "   Failed snapshots: $failed_count"

    local failed_details_json=""
    local i
    for i in "${!failed_urls[@]}"; do
        local entry="• ${failed_urls[$i]}\n  _${failed_reasons[$i]}_"
        if [ -z "$failed_details_json" ]; then
            failed_details_json="$entry"
        else
            failed_details_json="${failed_details_json}\n${entry}"
        fi
        log_message "   Failed: ${failed_urls[$i]} (${failed_reasons[$i]})"
    done

    send_discord_notification "$success_count" "$total_count" "$failed_count" "$failed_details_json" "$skipped_count"
    log_message "Daily snapshot process completed"

    find "$SCRIPT_DIR" -name "*.log" -mtime +"${LOG_RETENTION_DAYS:-90}" -delete 2>/dev/null || true

    if [ "$failed_count" -eq 0 ]; then
        exit 0
    fi
    exit 1
}

main "$@"
