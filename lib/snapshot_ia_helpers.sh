# Internet Archive SPN2 helpers for website_snapshot.sh
# Sourced after config and log_message() are defined.

append_common_ia_params() {
    local api_params="$1"
    if [ -n "$IF_NOT_ARCHIVED_WITHIN" ]; then
        api_params="${api_params}&if_not_archived_within=${IF_NOT_ARCHIVED_WITHIN}"
    fi
    if [ "$SKIP_FIRST_ARCHIVE" = "true" ]; then
        api_params="${api_params}&skip_first_archive=1"
    fi
    if [ "$DELAY_WB_AVAILABILITY" = "true" ]; then
        api_params="${api_params}&delay_wb_availability=1"
    fi
    echo "$api_params"
}

build_api_params() {
    local url="$1"
    local api_params
    api_params="url=${url}"

    if [ "$CAPTURE_OUTLINKS" = "true" ]; then
        api_params="${api_params}&capture_outlinks=1"
    fi
    if [ "$CAPTURE_SCREENSHOT" = "true" ]; then
        api_params="${api_params}&capture_screenshot=1"
    fi
    if [ "$CAPTURE_ALL" = "true" ]; then
        api_params="${api_params}&capture_all=1"
    fi
    if [ "$EMAIL_RESULT" = "true" ]; then
        api_params="${api_params}&email_result=1"
    fi
    if [ "$WACZ_FILE" = "true" ]; then
        api_params="${api_params}&wacz=1"
    fi

    append_common_ia_params "$api_params"
}

is_spn_rate_limit_response() {
    local http_code="$1"
    local body_snippet="$2"
    if [ "$http_code" = "429" ] || [ "$http_code" = "503" ]; then
        return 0
    fi
    case "$body_snippet" in
        *too-many-requests*|*too-many-daily-captures*)
            return 0
            ;;
    esac
    return 1
}

extract_spn_error_code() {
    local body_snippet="$1"
    if echo "$body_snippet" | grep -q 'error:too-many-daily-captures'; then
        echo "too-many-daily-captures"
    elif echo "$body_snippet" | grep -q 'error:too-many-requests'; then
        echo "too-many-requests"
    elif echo "$body_snippet" | grep -q 'too-many-daily-captures'; then
        echo "too-many-daily-captures"
    elif echo "$body_snippet" | grep -q 'too-many-requests'; then
        echo "too-many-requests"
    else
        echo ""
    fi
}

wayback_recent_capture_exists() {
    local url="$1"
    local response latest_timestamp capture_ts now_ts age_hours
    response=$(curl -s -G --connect-timeout "$CONNECT_TIMEOUT" --max-time "$REQUEST_TIMEOUT" \
        "https://web.archive.org/cdx/search/cdx" \
        --data-urlencode "url=$url" \
        --data "output=json" \
        --data "limit=1" \
        --data "filter=statuscode:200" 2>/dev/null)
    latest_timestamp=$(echo "$response" | grep -oE '"[0-9]{14}"' | head -1 | tr -d '"')
    if [ -z "$latest_timestamp" ] || [ "$latest_timestamp" = "null" ]; then
        return 1
    fi
    capture_ts=$(date -d "${latest_timestamp:0:8} ${latest_timestamp:8:2}:${latest_timestamp:10:2}:${latest_timestamp:12:2}" +%s 2>/dev/null) || return 1
    now_ts=$(date +%s)
    age_hours=$(( (now_ts - capture_ts) / 3600 ))
    if [ "$age_hours" -lt "$SKIP_IF_SNAPSHOT_WITHIN_HOURS" ]; then
        return 0
    fi
    return 1
}

preflight_archive_service() {
    if [ "$PREFLIGHT_IA_STATUS" != "true" ]; then
        return 0
    fi

    local body_file http_code body_snippet cache_bust
    cache_bust=$(date +%s)

    body_file=$(mktemp)
    http_code=$(curl -s -o "$body_file" -w "%{http_code}" \
        --connect-timeout "$CONNECT_TIMEOUT" \
        --max-time "$REQUEST_TIMEOUT" \
        "${IA_STATUS_SYSTEM_URL}?_t=${cache_bust}")
    body_snippet=$(head -c 500 "$body_file" | tr '\n' ' ')
    rm -f "$body_file"

    if [ "$http_code" = "502" ]; then
        log_message "Preflight skip: Save Page Now system returned HTTP 502"
        return 1
    fi
    case "$body_snippet" in
        *temporarily\ overloaded*|*overloaded*)
            log_message "Preflight skip: Save Page Now reports overloaded status"
            return 1
            ;;
    esac

    body_file=$(mktemp)
    http_code=$(curl -s -o "$body_file" -w "%{http_code}" \
        --connect-timeout "$CONNECT_TIMEOUT" \
        --max-time "$REQUEST_TIMEOUT" \
        -H "Authorization: LOW ${IA_ACCESS_KEY}:${IA_SECRET_KEY}" \
        -H "Accept: application/json" \
        "${IA_STATUS_USER_URL}?_t=${cache_bust}")
    body_snippet=$(head -c 200 "$body_file" | tr '\n' ' ')
    rm -f "$body_file"

    if [ "$http_code" != "200" ]; then
        log_message "Preflight warning: user status HTTP $http_code (continuing)"
        return 0
    fi
    if echo "$body_snippet" | grep -q '"available"[[:space:]]*:[[:space:]]*0'; then
        log_message "Preflight skip: no available Save Page Now user sessions (available=0)"
        return 1
    fi
    return 0
}

submit_to_archive() {
    local url="$1"
    local api_params
    api_params=$(build_api_params "$url")

    local body_file
    body_file=$(mktemp)
    local http_code
    http_code=$(curl -s -o "$body_file" -w "%{http_code}" \
        --connect-timeout "$CONNECT_TIMEOUT" \
        --max-time "$REQUEST_TIMEOUT" \
        -X POST \
        -H "Authorization: LOW ${IA_ACCESS_KEY}:${IA_SECRET_KEY}" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -H "Accept: application/json" \
        -d "$api_params" \
        "$IA_API_URL")

    local body_snippet
    body_snippet=$(head -c 500 "$body_file" | tr '\n' ' ')
    rm -f "$body_file"

    echo "${http_code}|${body_snippet}"
}

log_capture_options() {
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
    if [ -n "$IF_NOT_ARCHIVED_WITHIN" ]; then
        log_message "   if_not_archived_within=${IF_NOT_ARCHIVED_WITHIN}"
    fi
}

handle_rate_limit_hit() {
    local url="$1"
    local http_code="$2"
    local spn_error="$3"
    set_rate_limit_cooldown "$RATE_LIMIT_COOLDOWN_HOURS"
    RUN_HIT_RATE_LIMIT=1
    if [ -n "$spn_error" ]; then
        log_message "Rate limited on $url (HTTP $http_code, SPN error: $spn_error). No retries; cooldown ${RATE_LIMIT_COOLDOWN_HOURS}h."
    else
        log_message "Rate limited on $url (HTTP $http_code). No retries; cooldown ${RATE_LIMIT_COOLDOWN_HOURS}h."
    fi
}
