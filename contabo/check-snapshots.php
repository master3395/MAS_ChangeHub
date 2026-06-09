<?php
/**
 * Check Snapshot Data Structure
 */

define('CONTABO_SNAPSHOT_INIT', true);
require_once __DIR__ . '/config.php';

echo "Checking Snapshot Data Structure...\n";
echo "==================================\n\n";

// Authenticate
$data = [
    'grant_type' => 'password',
    'client_id' => CONTABO_CLIENT_ID,
    'client_secret' => CONTABO_CLIENT_SECRET,
    'username' => CONTABO_API_USER,
    'password' => CONTABO_API_PASSWORD
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => CONTABO_AUTH_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($data),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'x-request-id: ' . sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        )
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Authentication failed: HTTP $httpCode\n";
    exit(1);
}

$authData = json_decode($response, true);
$accessToken = $authData['access_token'];

echo "✅ Authentication successful\n\n";

// Get snapshots
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => CONTABO_API_BASE_URL . '/compute/instances/202441688/snapshots',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'x-request-id: ' . sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        )
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Failed to get snapshots: HTTP $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$snapshotsData = json_decode($response, true);

echo "Found " . count($snapshotsData['data']) . " snapshots:\n\n";

foreach ($snapshotsData['data'] as $i => $snapshot) {
    echo "Snapshot " . ($i + 1) . ":\n";
    echo json_encode($snapshot, JSON_PRETTY_PRINT) . "\n";
    echo "---\n\n";
}

echo "Done!\n";
?>
