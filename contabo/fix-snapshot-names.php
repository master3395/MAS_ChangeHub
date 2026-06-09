<?php
/**
 * Fix Snapshot Names
 * Renames snapshots that do not match SNAPSHOT_NAMES in config.php.
 */

define('CONTABO_SNAPSHOT_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/snapshot-names.php';

echo "Fixing Snapshot Names (from config.php)...\n";
echo "========================================\n\n";

$accessToken = contabo_fix_authenticate();
if ($accessToken === null) {
    exit(1);
}

echo "Configured names per instance:\n";
foreach (INSTANCE_NAMES as $instanceName) {
    $names = contabo_get_snapshot_names($instanceName);
    echo "  {$instanceName}: " . (empty($names) ? '(none)' : implode(', ', $names)) . "\n";
}
echo "\n";

$hadWork = false;

foreach (INSTANCE_NAMES as $instanceName) {
    $instanceId = contabo_fix_resolve_instance_id($accessToken, $instanceName);
    if ($instanceId === null) {
        continue;
    }

    echo "Instance: {$instanceName} (ID: {$instanceId})\n";
    echo str_repeat('-', 50) . "\n";

    $snapshots = contabo_fix_api_get($accessToken, CONTABO_API_BASE_URL . '/compute/instances/' . $instanceId . '/snapshots');
    if ($snapshots === null) {
        echo "  Failed to list snapshots.\n\n";
        continue;
    }

    $incorrect = [];
    $correct = [];

    foreach ($snapshots as $snapshot) {
        $name = $snapshot['name'] ?? '';
        if (contabo_is_managed_snapshot_name($name, $instanceName)) {
            $correct[] = $snapshot;
        } else {
            $incorrect[] = $snapshot;
        }
    }

    echo "  Correct: " . count($correct) . ", incorrect: " . count($incorrect) . "\n";

    if (empty($incorrect)) {
        echo "  All snapshots match config.\n\n";
        continue;
    }

    $hadWork = true;
    $usedNames = [];
    foreach ($correct as $snapshot) {
        $usedNames[] = $snapshot['name'];
    }

    foreach ($incorrect as $i => $snapshot) {
        $newName = contabo_pick_next_snapshot_name($instanceName, $usedNames);
        if ($newName === null) {
            echo "  No free name in SNAPSHOT_NAMES for instance {$instanceName}.\n";
            break;
        }
        $usedNames[] = $newName;

        echo "  Fix " . ($i + 1) . ": {$snapshot['name']} -> {$newName}\n";

        if (!contabo_fix_delete_snapshot($accessToken, $instanceId, $snapshot['snapshotId'])) {
            echo "    Delete failed.\n";
            continue;
        }

        sleep(2);

        if (contabo_fix_create_snapshot($accessToken, $instanceId, $newName)) {
            echo "    Created {$newName}.\n";
        } else {
            echo "    Create failed.\n";
        }
    }

    echo "\n";
}

if (!$hadWork) {
    echo "All snapshots already match config.php.\n";
} else {
    echo "Done. Re-run check-snapshots.php to verify.\n";
}

function contabo_fix_authenticate()
{
    $data = [
        'grant_type' => 'password',
        'client_id' => CONTABO_CLIENT_ID,
        'client_secret' => CONTABO_CLIENT_SECRET,
        'username' => CONTABO_API_USER,
        'password' => CONTABO_API_PASSWORD,
    ];

    $response = contabo_fix_curl(CONTABO_AUTH_URL, 'POST', $data, false);
    if ($response === null) {
        echo "Authentication failed.\n";
        return null;
    }

    $authData = json_decode($response['body'], true);
    if (empty($authData['access_token'])) {
        echo "Authentication failed: no access token.\n";
        return null;
    }

    echo "Authentication successful.\n\n";
    return $authData['access_token'];
}

function contabo_fix_resolve_instance_id($accessToken, $instanceName)
{
    $response = contabo_fix_api_get($accessToken, CONTABO_API_BASE_URL . '/compute/instances');
    if ($response === null) {
        echo "  Could not load instances.\n";
        return null;
    }

    foreach ($response as $instance) {
        if (($instance['displayName'] ?? '') === $instanceName) {
            return (string) ($instance['instanceId'] ?? $instance['id'] ?? '');
        }
    }

    echo "  Instance not found: {$instanceName}\n";
    return null;
}

function contabo_fix_api_get($accessToken, $url)
{
    $response = contabo_fix_curl($url, 'GET', null, true, $accessToken);
    if ($response === null || $response['code'] !== 200) {
        return null;
    }

    $data = json_decode($response['body'], true);
    return $data['data'] ?? [];
}

function contabo_fix_delete_snapshot($accessToken, $instanceId, $snapshotId)
{
    $url = CONTABO_API_BASE_URL . "/compute/instances/{$instanceId}/snapshots/{$snapshotId}";
    $response = contabo_fix_curl($url, 'DELETE', ['confirm' => true], true, $accessToken);

    return $response !== null && in_array($response['code'], [200, 204], true);
}

function contabo_fix_create_snapshot($accessToken, $instanceId, $name)
{
    $url = CONTABO_API_BASE_URL . "/compute/instances/{$instanceId}/snapshots";
    $payload = [
        'name' => $name,
        'description' => contabo_format_snapshot_description() . ' (renamed)',
    ];
    $response = contabo_fix_curl($url, 'POST', $payload, true, $accessToken);

    if ($response === null || !in_array($response['code'], [200, 201], true)) {
        return false;
    }

    $data = json_decode($response['body'], true);
    return isset($data['data'][0]['snapshotId']);
}

function contabo_fix_curl($url, $method, $body, $json = true, $accessToken = null)
{
    $ch = curl_init();
    $headers = [
        'x-request-id: ' . sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        ),
    ];

    if ($json) {
        $headers[] = 'Content-Type: application/json';
    } else {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    if ($accessToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $json ? json_encode($body) : http_build_query($body);
    } elseif ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($ch, $opts);
    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return null;
    }

    return ['code' => $httpCode, 'body' => (string) $responseBody];
}
