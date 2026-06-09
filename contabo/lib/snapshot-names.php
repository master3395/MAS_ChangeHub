<?php
/**
 * Snapshot naming helpers (reads SNAPSHOT_NAMES from config.php).
 */

if (!defined('CONTABO_SNAPSHOT_INIT')) {
    exit;
}

/**
 * Configured snapshot names for an instance (Contabo display name).
 *
 * @return string[]
 */
function contabo_get_snapshot_names($instanceName)
{
    if (defined('SNAPSHOT_NAMES_BY_INSTANCE') && is_array(SNAPSHOT_NAMES_BY_INSTANCE)) {
        if (isset(SNAPSHOT_NAMES_BY_INSTANCE[$instanceName]) && is_array(SNAPSHOT_NAMES_BY_INSTANCE[$instanceName])) {
            return array_values(SNAPSHOT_NAMES_BY_INSTANCE[$instanceName]);
        }
    }

    if (defined('SNAPSHOT_NAMES') && is_array(SNAPSHOT_NAMES)) {
        return array_values(SNAPSHOT_NAMES);
    }

    return [];
}

/**
 * Pick the next unused name from the configured list.
 */
function contabo_pick_next_snapshot_name($instanceName, array $existingSnapshotNames)
{
    $configured = contabo_get_snapshot_names($instanceName);
    if (empty($configured)) {
        return null;
    }

    $existing = array_flip(array_map('strval', $existingSnapshotNames));
    foreach ($configured as $name) {
        if (!isset($existing[(string) $name])) {
            return (string) $name;
        }
    }

    return (string) $configured[0];
}

/**
 * Whether a snapshot name is one we manage for this instance.
 */
function contabo_is_managed_snapshot_name($name, $instanceName)
{
    $configured = contabo_get_snapshot_names($instanceName);
    return in_array((string) $name, $configured, true);
}

/**
 * Build snapshot description from template.
 */
function contabo_format_snapshot_description()
{
    $template = defined('SNAPSHOT_DESCRIPTION_TEMPLATE')
        ? SNAPSHOT_DESCRIPTION_TEMPLATE
        : 'Daily backup {date} Oslo';

    $date = date('Y-m-d H:i:s');

    return str_replace('{date}', $date, $template);
}

/**
 * Warn in log when name count does not match retention limit.
 */
function contabo_validate_snapshot_name_config($logCallback)
{
    $max = defined('MAX_SNAPSHOTS_PER_INSTANCE') ? (int) MAX_SNAPSHOTS_PER_INSTANCE : 3;
    $instances = defined('INSTANCE_NAMES') && is_array(INSTANCE_NAMES) ? INSTANCE_NAMES : [];

    foreach ($instances as $instanceName) {
        $names = contabo_get_snapshot_names($instanceName);
        $count = count($names);
        if ($count === 0) {
            $logCallback('ERROR: No SNAPSHOT_NAMES configured for instance: ' . $instanceName);
            continue;
        }
        if ($count !== $max) {
            $logCallback(
                'WARNING: Instance ' . $instanceName . ' has ' . $count
                . ' snapshot name(s) but MAX_SNAPSHOTS_PER_INSTANCE is ' . $max
            );
        }
    }
}
