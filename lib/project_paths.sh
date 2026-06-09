# MAS ChangeHub shared paths. Set MAS_CHANGEHUB_ROOT before sourcing.
if [ -z "${MAS_CHANGEHUB_ROOT:-}" ]; then
    echo "MAS_CHANGEHUB_ROOT is not set" >&2
    return 1 2>/dev/null || exit 1
fi

MAS_LOG_DIR="$MAS_CHANGEHUB_ROOT/logs"
MAS_CONFIG_DIR="$MAS_CHANGEHUB_ROOT/config"
MAS_STATE_DIR="$MAS_CHANGEHUB_ROOT/state"
MAS_ARCHIVE_DIR="$MAS_CHANGEHUB_ROOT/archive"
MAS_TEST_DIR="$MAS_CHANGEHUB_ROOT/test"
MAS_TEST_CONTABO_DIR="$MAS_TEST_DIR/contabo"
MAS_CONTABO_DIR="$MAS_CHANGEHUB_ROOT/contabo"

MAS_SNAPSHOT_LOG="$MAS_LOG_DIR/snapshot.log"
MAS_TEST_SNAPSHOT_LOG="$MAS_LOG_DIR/test_snapshot.log"
MAS_SNAPSHOT_CONFIG="$MAS_CONFIG_DIR/snapshot_config.conf"
MAS_SNAPSHOT_SCRIPT="$MAS_CHANGEHUB_ROOT/website_snapshot.sh"

mkdir -p "$MAS_LOG_DIR" "$MAS_STATE_DIR"
