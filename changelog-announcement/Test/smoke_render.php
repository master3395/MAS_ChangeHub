<?php
/**
 * Smoke test: render the Components V2 payload for every configured channel
 * (latest entry), without contacting Discord. Exits 0 if every channel built
 * a payload; exits 1 otherwise.
 *
 * Run: php Test/smoke_render.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "CLI only\n"; exit; }

define('CHANGELOG_ANNOUNCEMENT_APP_INIT', true);
$root = dirname(__DIR__);
require_once $root . '/modules/channels/channel_registry.php';
require_once $root . '/modules/renderer/futuristic_layout.php';

$config = require $root . '/config.php';
if (!is_array($config)) {
    fwrite(STDERR, "config.php did not return array\n");
    exit(1);
}

$reg = new Changelog_Channel_Registry($config);
$defaults = $reg->defaults();
$failures = 0;
foreach ($reg->listNames() as $name) {
    try {
        $ch  = $reg->get($name);
        $src = $reg->buildSource($name);
        $entry = $src->fetchLatest();
        if (!$entry) {
            printf("[skip] %s: no entry found (%s)\n", $name, $src->describe());
            continue;
        }
        $payload = Changelog_Futuristic_Layout::build($entry, $ch, $defaults);
        $bytes = strlen((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $components = $payload['components'][0]['components'] ?? [];
        printf("[ok]   %-14s v%-8s %s  components=%d  size=%d B\n",
            $name, $entry['version'] ?? '?', $entry['release_date'] ?? '?',
            count($components), $bytes);
    } catch (Throwable $e) {
        $failures++;
        printf("[fail] %s: %s\n", $name, $e->getMessage());
    }
}
exit($failures > 0 ? 1 : 0);
