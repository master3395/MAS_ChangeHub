<?php

define('CONTABO_SNAPSHOT_INIT', true);
require_once dirname(__DIR__, 2) . '/contabo/config.php';
require_once dirname(__DIR__) . '/lib/discord_notify_contabo.php';

echo "Testing Contabo Discord Webhook Integration...\n";

if (!DISCORD_WEBHOOK_ENABLED || empty(DISCORD_WEBHOOK_URL)) {
    echo "Discord webhook disabled or URL not set in config.php\n";
    exit(1);
}

$result = discord_notify_contabo_send([
    'webhook_url' => DISCORD_WEBHOOK_URL,
    'use_cv2' => defined('DISCORD_USE_CV2') ? DISCORD_USE_CV2 : true,
    'success' => true,
    'stats' => [
        'instances_processed' => 1,
        'snapshots_created' => 1,
        'snapshots_deleted' => 0,
        'total_snapshots' => 3,
        'errors' => 0,
        'snapshot_details' => [[
            'name' => 'test-snapshot',
            'createdDate' => gmdate('c'),
            'autoDeleteDate' => gmdate('c', strtotime('+7 days')),
        ]],
    ],
    'instances' => [['name' => 'newstargeted.com', 'id' => '202441688']],
    'timezone' => defined('TIMEZONE') ? TIMEZONE : 'Europe/Oslo',
    'hero_image_url' => defined('DISCORD_HERO_IMAGE_URL') ? DISCORD_HERO_IMAGE_URL : 'https://newstargeted.com/assets/status-cv2/contabo.png',
    'username' => defined('DISCORD_WEBHOOK_USERNAME') ? DISCORD_WEBHOOK_USERNAME : 'Contabo Snapshot Manager',
    'panel_url' => defined('CONTABO_PANEL_URL') ? CONTABO_PANEL_URL : 'https://my.contabo.com/',
    'error_log_path' => defined('ERROR_LOG_FILE') ? ERROR_LOG_FILE : '',
]);

if (!empty($result['ok'])) {
    echo "Test notification sent successfully.\n";
    exit(0);
}

$message = $result['error'] ?? ('HTTP ' . ($result['http_code'] ?? 'unknown'));
echo "Failed: $message\n";
exit(1);
