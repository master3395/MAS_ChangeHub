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
SKIP_FIRST_ARCHIVE="${SKIP_FIRST_ARCHIVE:-false}"
EMAIL_RESULT="${EMAIL_RESULT:-false}"
WACZ_FILE="${WACZ_FILE:-false}"
DOMAIN_SELECTION_MODE="${DOMAIN_SELECTION_MODE:-main}"
MAIN_DOMAIN="${MAIN_DOMAIN:-https://newstargeted.com}"
DISCORD_WEBHOOK_ENABLED="${DISCORD_WEBHOOK_ENABLED:-true}"
DISCORD_NOTIFY_ON_FAILURE_ONLY="${DISCORD_NOTIFY_ON_FAILURE_ONLY:-true}"
DISCORD_NOTIFY_ON_SUCCESS="${DISCORD_NOTIFY_ON_SUCCESS:-false}"
REQUEST_TIMEOUT="${REQUEST_TIMEOUT:-60}"
CONNECT_TIMEOUT="${CONNECT_TIMEOUT:-30}"
DELAY_BETWEEN_REQUESTS="${DELAY_BETWEEN_REQUESTS:-5}"
MAX_RETRIES="${MAX_RETRIES:-3}"
RETRY_BASE_DELAY_SECONDS="${RETRY_BASE_DELAY_SECONDS:-120}"
RATE_LIMIT_COOLDOWN_HOURS="${RATE_LIMIT_COOLDOWN_HOURS:-12}"
SKIP_IF_SNAPSHOT_WITHIN_HOURS="${SKIP_IF_SNAPSHOT_WITHIN_HOURS:-20}"
FALLBACK_SIMPLE_ON_RATE_LIMIT="${FALLBACK_SIMPLE_ON_RATE_LIMIT:-true}"
IA_API_URL="${IA_API_URL:-https://web.archive.org/save/}"

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

build_api_params() {
    local url="$1"
    local mode="$2"
    local api_params="url=${url}"

    if [ "$mode" = "simple" ]; then
        echo "$api_params"
        return
    fi

    if [ "$CAPTURE_OUTLINKS" = "true" ]; then
        api_params="${api_params}&capture_outlinks=1"
    fi
    if [ "$CAPTURE_SCREENSHOT" = "true" ]; then
        api_params="${api_params}&capture_screenshot=1"
    fi
    if [ "$CAPTURE_ALL" = "true" ]; then
        api_params="${api_params}&capture_all=1"
    fi
    if [ "$SKIP_FIRST_ARCHIVE" = "true" ]; then
        api_params="${api_params}&skip_first_archive=1"
    fi
    if [ "$EMAIL_RESULT" = "true" ]; then
        api_params="${api_params}&email_result=1"
    fi
    if [ "$WACZ_FILE" = "true" ]; then
        api_params="${api_params}&wacz=1"
    fi

    echo "$api_params"
}

submit_to_archive() {
    local url="$1"
    local mode="$2"
    local api_params
    api_params=$(build_api_params "$url" "$mode")

    local body_file
    body_file=$(mktemp)
    local http_code
    http_code=$(curl -s -o "$body_file" -w "%{http_code}" \
        --connect-timeout "$CONNECT_TIMEOUT" \
        --max-time "$REQUEST_TIMEOUT" \
        -X POST \
        -H "Authorization: LOW ${IA_ACCESS_KEY}:${IA_SECRET_KEY}" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "$api_params" \
        "$IA_API_URL")

    local body_snippet
    body_snippet=$(head -c 200 "$body_file" | tr '\n' ' ')
    rm -f "$body_file"

    echo "${http_code}|${body_snippet}"
}

log_capture_options() {
    local mode="$1"
    if [ "$mode" = "simple" ]; then
        log_message "   Using simple capture (URL only)"
        return
    fi
    if [ "$CAPTURE_OUTLINKS" = "true" ]; then
        log_message "   Capturing outlinks"
    fi
    if [ "$CAPTURE_SCREENSHOT" = "true" ]; then
        log_message "   Capturing screenshot"
    fi
    if [ "$CAPTURE_ALL" = "true" ]; then
        log_message "   Capturing error pages"
    fi
    if [ "$SKIP_FIRST_ARCHIVE" = "true" ]; then
        log_message "   skip_first_archive enabled"
    fi
}

create_snapshot() {
    local url="$1"
    local -n failure_reason_ref=$2
    local attempt=1
    local delay="$RETRY_BASE_DELAY_SECONDS"
    local modes=("enhanced")

    if [ "$FALLBACK_SIMPLE_ON_RATE_LIMIT" = "true" ]; then
        modes+=("simple")
    fi

    log_message "Creating snapshot for: $url"

    if is_rate_limited_cooldown; then
        failure_reason_ref="Skipped: Internet Archive rate limit cooldown (avoiding HTTP 429)"
        log_message "Skipping $url: $failure_reason_ref"
        return 0
    fi

    if recent_snapshot_recorded "$url"; then
        failure_reason_ref="Skipped: successful snapshot within last ${SKIP_IF_SNAPSHOT_WITHIN_HOURS}h"
        log_message "Skipping $url ($failure_reason_ref)"
        return 0
    fi

    for mode in "${modes[@]}"; do
        log_capture_options "$mode"
        attempt=1
        delay="$RETRY_BASE_DELAY_SECONDS"

        while [ "$attempt" -le "$MAX_RETRIES" ]; do
            local result http_code body_snippet
            result=$(submit_to_archive "$url" "$mode")
            http_code="${result%%|*}"
            body_snippet="${result#*|}"

            if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
                log_message "Successfully submitted $url ($mode, HTTP $http_code)"
                record_success "$url" "$mode"
                rm -f "$RATE_LIMIT_FILE"
                failure_reason_ref=""
                return 0
            fi

            if [ "$http_code" = "429" ] || [ "$http_code" = "503" ]; then
                set_rate_limit_cooldown "$RATE_LIMIT_COOLDOWN_HOURS"
                failure_reason_ref="Internet Archive rate limited (HTTP $http_code). Too many Save-Page-Now requests."
                log_message "Rate limited on $url ($mode, HTTP $http_code, attempt $attempt/$MAX_RETRIES)"

                if [ "$attempt" -lt "$MAX_RETRIES" ]; then
                    log_message "   Waiting ${delay}s before retry..."
                    sleep "$delay"
                    delay=$((delay * 2))
                    attempt=$((attempt + 1))
                    continue
                fi
                break
            fi

            failure_reason_ref="Internet Archive error (HTTP $http_code): ${body_snippet}"
            log_message "Failed to submit $url ($mode, HTTP $http_code): ${body_snippet}"
            return 1
        done

        if [ "$mode" = "enhanced" ] && [ "$FALLBACK_SIMPLE_ON_RATE_LIMIT" = "true" ]; then
            log_message "   Falling back to simple capture for $url"
        fi
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

    local status_emoji status_text color
    if [ "$failed_count" -eq 0 ]; then
        status_emoji="✅"
        status_text="Completed Successfully"
        color=3066993
    elif [ "$success_count" -gt 0 ] || [ "$skipped_count" -gt 0 ]; then
        status_emoji="⚠️"
        status_text="Completed with Warnings"
        color=16776960
    else
        status_emoji="❌"
        status_text="Completed with Errors"
        color=15158332
    fi

    local timestamp timestamp_display
    timestamp=$(date -u '+%Y-%m-%dT%H:%M:%S.000Z')
    timestamp_display=$(date '+%Y-%m-%d %H:%M:%S %Z')

    local stats_value="**Total Websites:** $total_count\n**Successful Snapshots:** $success_count\n**Failed Snapshots:** $failed_count"
    if [ "$skipped_count" -gt 0 ]; then
        stats_value="${stats_value}\n**Skipped (recent/cooldown):** $skipped_count"
    fi

    local fields
    fields="[{\"name\":\"📊 Statistics\",\"value\":\"$stats_value\",\"inline\":false}"

    local websites_list="• [newstargeted.com](https://web.archive.org/web/*/https://newstargeted.com/) - Main domain\n• [api.newstargeted.com](https://web.archive.org/web/*/https://api.newstargeted.com/)\n• [infoskjerm.newstargeted.com](https://web.archive.org/web/*/https://infoskjerm.newstargeted.com/)\n• [mas.newstargeted.com](https://web.archive.org/web/*/https://mas.newstargeted.com/)\n• [discord.newstargeted.com](https://web.archive.org/web/*/https://discord.newstargeted.com/)\n• and 10 more domains..."
    fields="${fields},{\"name\":\"🌐 Websites Archived\",\"value\":\"$websites_list\",\"inline\":false}"

    local options_info=""
    if [ "$CAPTURE_OUTLINKS" = "true" ]; then
        options_info="${options_info}• Outlinks captured\n"
    fi
    if [ "$CAPTURE_SCREENSHOT" = "true" ]; then
        options_info="${options_info}• Screenshots taken\n"
    fi
    if [ "$CAPTURE_ALL" = "true" ]; then
        options_info="${options_info}• Error pages included\n"
    fi
    if [ "$SKIP_FIRST_ARCHIVE" = "true" ]; then
        options_info="${options_info}• skip_first_archive enabled\n"
    fi
    if [ -z "$options_info" ]; then
        options_info="• Simple capture (URL only) to reduce Internet Archive rate limits\n"
    fi
    fields="${fields},{\"name\":\"⚙️ Capture Options\",\"value\":\"$options_info\",\"inline\":false}"

    if [ "$failed_count" -gt 0 ] && [ -n "$failed_details_json" ]; then
        fields="${fields},{\"name\":\"⚠️ Failed URLs\",\"value\":\"$failed_details_json\",\"inline\":false}"
    fi

    fields="${fields},{\"name\":\"📦 View All Snapshots\",\"value\":\"Click any domain link above to view its Wayback Machine calendar with all historical snapshots.\",\"inline\":false}]"

    local payload
    payload=$(cat <<EOF
{
  "embeds": [{
    "title": "$status_emoji Internet Archive Snapshot Manager - $status_text",
    "description": "Daily snapshot management completed at **$timestamp_display**",
    "color": $color,
    "fields": $fields,
    "footer": {
      "text": "Internet Archive Snapshot Manager v1.1 • newstargeted.com"
    },
    "timestamp": "$timestamp"
  }]
}
EOF
)

    log_message "Sending Discord notification..."
    local response http_code
    response=$(curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d "$payload" \
        "$DISCORD_WEBHOOK_URL")
    http_code=$(echo "$response" | tail -n1)

    if [ "$http_code" = "204" ] || [ "$http_code" = "200" ]; then
        log_message "Discord notification sent successfully"
    else
        log_message "Failed to send Discord notification: HTTP $http_code"
    fi
}

main() {
    log_message "Starting daily website snapshot process"
    log_message "Date: $(date '+%Y-%m-%d %H:%M:%S %Z')"
    log_message "Domain mode: $DOMAIN_SELECTION_MODE (${#SNAPSHOT_URLS[@]} domains)"

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
