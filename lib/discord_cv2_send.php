#!/usr/bin/env php
<?php

define('MAS_CHANGEHUB_CV2', true);

$root = dirname(__DIR__);
require_once $root . '/lib/discord_notify_archive.php';

$raw = stream_get_contents(STDIN);
if ($raw === false || trim($raw) === '') {
    fwrite(STDERR, "discord_cv2_send.php: empty stdin\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "discord_cv2_send.php: invalid JSON\n");
    exit(1);
}

$result = discord_notify_archive_send($data);
if (empty($result['ok'])) {
    $message = $result['error'] ?? ('HTTP ' . ($result['http_code'] ?? 'unknown'));
    fwrite(STDERR, "discord_cv2_send.php: failed: $message\n");
    exit(1);
}

echo "ok\n";
exit(0);
