<?php
/**
 * Changelog Announcer CLI.
 *
 * Subcommands:
 *   help                                  Show usage.
 *   list                                  List known channels and where they read from.
 *   show     --channel=NAME [--version=X] Render the payload as JSON (no send).
 *   preview  --channel=NAME [--version=X] Pretty-print the visible text fields.
 *   send     --channel=NAME [--version=X] [--force] [--no-wait]
 *                                         [--ping-roles=ID,ID] [--ping-users=ID,ID]
 *                                         [--no-pings] [--to=url1,url2] [--quiet]
 *                                         POST to every configured webhook for the channel.
 *
 * Common flags:
 *   --channel=NAME           Required (except for `help` and `list`).
 *   --version=X              Specific version; default = latest in source.
 *   --force                  Allow re-announcing the same {channel,version}.
 *   --no-wait                Send fire-and-forget (no Discord ack).
 *   --ping-roles=ID,ID,...   Additional role IDs to ping for this send.
 *   --ping-users=ID,ID,...   Additional user IDs to ping for this send.
 *   --no-pings               Suppress every ping for this send (silent post).
 *   --to=URL,URL,...         Override destination(s) for this send; bypasses
 *                            the channel's configured webhook list.
 *   --quiet                  Reduce CLI output.
 *
 * Exit codes:
 *   0 OK,  1 usage,  2 source error,  3 webhook error,  4 already announced,
 *   5 missing webhook URL.
 *
 * Examples:
 *   php cli.php list
 *   php cli.php show    --channel=webhook-proxy --version=4.0.10
 *   php cli.php preview --channel=webhook-proxy
 *   php cli.php send    --channel=webhook-proxy --version=4.0.10
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit;
}

define('CHANGELOG_ANNOUNCEMENT_APP_INIT', true);

require_once __DIR__ . '/modules/channels/channel_registry.php';
require_once __DIR__ . '/modules/renderer/futuristic_layout.php';
require_once __DIR__ . '/modules/webhook/discord_webhook.php';
require_once __DIR__ . '/modules/webhook/announcement_log.php';
require_once __DIR__ . '/modules/emoji/emoji_registry.php';
require_once __DIR__ . '/modules/emoji/emoji_sync.php';

$config = require __DIR__ . '/config.php';
if (!is_array($config)) {
    fwrite(STDERR, "config.php did not return an array\n");
    exit(1);
}

// Make the emoji registry available to the renderer before any layout
// is built. Renderer falls back to Unicode when an emoji has no app id.
$emojiRegPath = (string) ($config['defaults']['emoji_registry_file'] ?? __DIR__ . '/assets/emoji-registry.json');
$emojiReg = new Changelog_Emoji_Registry($emojiRegPath, __DIR__);
Changelog_Futuristic_Layout::useEmojiRegistry($emojiReg);

[$cmd, $opts] = changelog_announcement_parse_argv($argv);

try {
    switch ($cmd) {
        case 'help':
        case '--help':
        case '-h':
        case '':
            changelog_announcement_print_help();
            exit(0);
        case 'list':
            changelog_announcement_cmd_list($config);
            exit(0);
        case 'show':
            exit(changelog_announcement_cmd_show($config, $opts));
        case 'preview':
            exit(changelog_announcement_cmd_preview($config, $opts));
        case 'send':
            exit(changelog_announcement_cmd_send($config, $opts));
        case 'sync-emojis':
        case 'emojis':
            exit(changelog_announcement_cmd_sync_emojis($config, $emojiReg, $opts));
        case 'emoji-list':
            exit(changelog_announcement_cmd_emoji_list($emojiReg));
        default:
            fwrite(STDERR, "Unknown command: $cmd\nRun: php cli.php help\n");
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "[error] " . $e->getMessage() . "\n");
    exit(2);
}

/* ====================================================================== */

function changelog_announcement_print_help(): void
{
    $me = basename(__FILE__);
    echo <<<TXT
Changelog Announcer

Usage:
  php $me help
  php $me list
  php $me show         --channel=NAME [--version=X]
  php $me preview      --channel=NAME [--version=X]
  php $me send         --channel=NAME [--version=X] [--force] [--no-wait]
                       [--ping-roles=ID,ID] [--ping-users=ID,ID] [--no-pings]
                       [--to=URL,URL] [--quiet]
  php $me emoji-list                              Show emoji registry state
  php $me sync-emojis                             Upload local PNGs as Discord
                                                  application emojis (requires
                                                  app id + bot token in config)

Examples:
  php $me list
  php $me emoji-list
  php $me sync-emojis
  php $me preview --channel=webhook-proxy
  php $me send    --channel=webhook-proxy --version=4.0.10

TXT;
}

function changelog_announcement_cmd_emoji_list(Changelog_Emoji_Registry $reg): int
{
    $names = $reg->names();
    printf("Registered emojis (%d):\n", count($names));
    foreach ($names as $n) {
        $row = $reg->get($n) ?? [];
        $id  = trim((string) ($row['application_emoji_id'] ?? ''));
        $loc = (string) ($row['local_file'] ?? '');
        printf("  - %-22s  app_emoji_id=%-20s  fallback=%s  file=%s\n",
            $n, $id !== '' ? $id : '(not uploaded)',
            (string) ($row['fallback'] ?? '-'),
            $loc);
    }
    return 0;
}

function changelog_announcement_cmd_sync_emojis(array $config, Changelog_Emoji_Registry $reg, array $opts): int
{
    $appCfg = (array) ($config['discord_app'] ?? []);
    $appId  = (string) ($appCfg['app_id'] ?? '');
    $token  = (string) ($appCfg['bot_token'] ?? '');
    if ($appId === '' || $token === '') {
        fwrite(STDERR, "[error] sync-emojis: set CHANGELOG_ANNOUNCEMENT_DISCORD_APP_ID and CHANGELOG_ANNOUNCEMENT_DISCORD_BOT_TOKEN (in bin/announce-changelog.env or config.php)\n");
        return 6;
    }
    $sync = new Changelog_Emoji_Sync($appId, $token, $reg);
    $results = $sync->syncAll();
    foreach ($results as $r) {
        printf("  %-22s  %-7s  id=%-20s  http=%-3d  %s\n",
            $r['name'], $r['action'], $r['id'] !== '' ? $r['id'] : '-',
            $r['http'], $r['error']);
    }
    $fail = 0;
    foreach ($results as $r) if ($r['action'] === 'fail') $fail++;
    return $fail > 0 ? 6 : 0;
}

function changelog_announcement_cmd_list(array $config): void
{
    $reg = new Changelog_Channel_Registry($config);
    $names = $reg->listNames();
    printf("Configured channels (%d):\n", count($names));
    foreach ($names as $name) {
        $ch = $reg->get($name);
        $urls = $reg->webhookUrls($name);
        // Count every role + user that would be pinged for this channel.
        $roleCount = 0;
        if (trim((string) ($ch['notify_role_id'] ?? '')) !== '') $roleCount++;
        $roleCount += count((array) ($ch['notify_role_ids'] ?? []));
        $userCount = count((array) ($ch['notify_user_ids'] ?? []));
        $brand = [];
        if (trim((string) ($ch['logo_url'] ?? '')) !== '')        $brand[] = 'logo';
        if (trim((string) ($ch['thumbnail_url'] ?? '')) !== '')   $brand[] = 'thumb';
        if (trim((string) ($ch['avatar_url'] ?? '')) !== '')      $brand[] = 'avatar';
        if (trim((string) ($ch['webhook_username'] ?? '')) !== '')$brand[] = 'name';
        if (trim((string) ($ch['footer_message'] ?? '')) !== '')  $brand[] = 'footer';
        $brandStr = $brand ? implode(',', $brand) : '-';
        $src = $reg->buildSource($name);
        printf("  - %-16s  webhooks=%-2d  roles=%-2d  users=%-2d  branding=%-22s  source=%s\n",
            $name, count($urls), $roleCount, $userCount, $brandStr, $src->describe());
    }
}

/** @return array<string,mixed> */
function changelog_announcement_resolve_entry(array $config, array $opts): array
{
    $reg = new Changelog_Channel_Registry($config);
    $name = (string) ($opts['channel'] ?? '');
    if ($name === '') {
        throw new InvalidArgumentException('--channel is required');
    }
    $ch = $reg->get($name);
    $src = $reg->buildSource($name);
    $version = isset($opts['version']) ? (string) $opts['version'] : '';
    $entry = $version !== '' ? $src->fetchByVersion($version) : $src->fetchLatest();
    if (!$entry) {
        $where = $version !== '' ? ('version ' . $version) : 'latest entry';
        throw new RuntimeException("No $where found in source for channel '$name' (" . $src->describe() . ")");
    }
    return [
        'name'     => $name,
        'channel'  => $ch,
        'entry'    => $entry,
        'defaults' => $reg->defaults(),
    ];
}

function changelog_announcement_cmd_show(array $config, array $opts): int
{
    $r = changelog_announcement_resolve_entry($config, $opts);
    $overrides = changelog_announcement_ping_overrides($opts);
    $payload = Changelog_Futuristic_Layout::build($r['entry'], $r['channel'], $r['defaults'], $overrides);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    return 0;
}

function changelog_announcement_cmd_preview(array $config, array $opts): int
{
    $r = changelog_announcement_resolve_entry($config, $opts);
    $overrides = changelog_announcement_ping_overrides($opts);
    $payload = Changelog_Futuristic_Layout::build($r['entry'], $r['channel'], $r['defaults'], $overrides);
    $container = $payload['components'][0] ?? [];
    $reg = new Changelog_Channel_Registry($config);
    $urls = $reg->webhookUrls($r['name']);
    echo "--- Components V2 preview ---\n";
    echo sprintf("Channel : %s (%s)\n", $r['name'], $r['channel']['project_tag'] ?? '?');
    echo sprintf("Webhooks: %d destination(s)\n", count($urls));
    foreach ($urls as $u) {
        $whId = preg_match('#/webhooks/(\d+)/#', $u, $m) ? $m[1] : '?';
        echo sprintf("          - %s\n", $whId);
    }
    echo sprintf("Entry   : v%s  released %s\n",
        $r['entry']['version'] ?? '?', $r['entry']['release_date'] ?? '?');
    echo "Flags   : IS_COMPONENTS_V2 = " . Changelog_Components_V2::FLAG_IS_COMPONENTS_V2 . "\n";
    echo "Accent  : #" . sprintf('%06X', (int) ($container['accent_color'] ?? 0)) . "\n";
    echo str_repeat('-', 60) . "\n";
    foreach ((array) ($container['components'] ?? []) as $c) {
        changelog_announcement_print_component($c, '');
    }
    echo str_repeat('-', 60) . "\n";
    echo "DRY RUN: this is a preview only. Nothing was posted to Discord.\n";
    $sendHint = 'php cli.php send --channel=' . $r['name'];
    if (isset($opts['version'])) $sendHint .= ' --version=' . (string) $opts['version'];
    echo "To actually post: " . $sendHint . "\n";
    return 0;
}

function changelog_announcement_print_component(array $c, string $prefix): void
{
    $type = (int) ($c['type'] ?? 0);
    switch ($type) {
        case 10:
            echo $prefix . "[TextDisplay]\n";
            foreach (explode("\n", (string) ($c['content'] ?? '')) as $line) {
                echo $prefix . '  ' . $line . "\n";
            }
            break;
        case 14:
            echo $prefix . "[Separator]\n";
            break;
        case 9:
            echo $prefix . "[Section]\n";
            foreach ((array) ($c['components'] ?? []) as $kid) {
                changelog_announcement_print_component($kid, $prefix . '  ');
            }
            break;
        case 1:
            echo $prefix . "[ActionRow]\n";
            foreach ((array) ($c['components'] ?? []) as $btn) {
                echo $prefix . sprintf("  - Button: %s -> %s\n",
                    (string) ($btn['label'] ?? '?'),
                    (string) ($btn['url'] ?? '?'));
            }
            break;
        default:
            echo $prefix . "[type=$type]\n";
    }
}

function changelog_announcement_cmd_send(array $config, array $opts): int
{
    $r = changelog_announcement_resolve_entry($config, $opts);
    $defaults = $r['defaults'];
    $quiet = !empty($opts['quiet']);
    $force = !empty($opts['force']);
    $withWait = empty($opts['no-wait']);

    // Resolve target URL list: CLI override --to=url[,url...] wins; otherwise
    // pull the channel's full list.
    $reg = new Changelog_Channel_Registry($config);
    $override = changelog_announcement_split_csv((string) ($opts['to'] ?? ''));
    $urls = $override ?: $reg->webhookUrls($r['name']);
    $urls = array_values(array_unique(array_filter($urls, function ($u) {
        return is_string($u) && preg_match('#^https://discord(?:app)?\.com/api/webhooks/\d+/[A-Za-z0-9_-]+#', $u);
    })));

    if (!$urls) {
        fwrite(STDERR, "[error] no Discord webhook URL configured for channel '{$r['name']}'\n");
        return 5;
    }

    $logPath = (string) ($defaults['announcement_log_file'] ?? __DIR__ . '/logs/announcements.jsonl');
    $log = new Changelog_Announcement_Log($logPath);

    // Dedupe key is per-channel only (not per-URL): the channel represents
    // one logical announcement, even if it fans out to several destinations.
    $existing = $log->find($r['name'], (string) ($r['entry']['version'] ?? ''));
    if ($existing && !$force && !empty($existing['ok'])) {
        fwrite(STDERR, sprintf(
            "[skip] %s v%s was already announced at %s (%d destination%s). Use --force to re-send.\n",
            $r['name'], $r['entry']['version'] ?? '?',
            $existing['ts'] ?? '?',
            count((array) ($existing['destinations'] ?? [])) ?: 1,
            (count((array) ($existing['destinations'] ?? [])) === 1 ? '' : 's')
        ));
        return 4;
    }

    $overrides = changelog_announcement_ping_overrides($opts);
    $payload = Changelog_Futuristic_Layout::build($r['entry'], $r['channel'], $defaults, $overrides);

    $client = new Changelog_Discord_Webhook(
        (int) ($defaults['http_timeout_s'] ?? 12),
        (int) ($defaults['http_retries'] ?? 3),
        (int) ($defaults['http_retry_ms'] ?? 1500)
    );

    $destResults = [];
    $allOk = true;
    foreach ($urls as $url) {
        $res = $client->send($url, $payload, $withWait);
        // Identify the destination by webhook id only (token kept private).
        if (preg_match('#/webhooks/(\d+)/#', $url, $m)) {
            $whId = $m[1];
        } else {
            $whId = '';
        }
        $destResults[] = [
            'webhook_id' => $whId,
            'ok'         => $res['ok'],
            'http'       => $res['http'],
            'attempts'   => $res['attempts'],
            'error'      => $res['error'],
            'message_id' => $res['message_id'],
        ];
        if (!$res['ok']) $allOk = false;
        if (!$quiet) {
            if ($res['ok']) {
                printf("[sent] channel=%s version=%s webhook=%s message_id=%s http=%d attempts=%d\n",
                    $r['name'], $r['entry']['version'] ?? '?', $whId,
                    $res['message_id'] !== '' ? $res['message_id'] : '(no-wait)',
                    $res['http'], $res['attempts']);
            } else {
                fwrite(STDERR, sprintf(
                    "[fail] channel=%s version=%s webhook=%s http=%d attempts=%d error=%s\nresponse: %s\n",
                    $r['name'], $r['entry']['version'] ?? '?', $whId,
                    $res['http'], $res['attempts'], $res['error'],
                    substr((string) $res['raw'], 0, 500)
                ));
            }
        }
    }

    $firstOk = null;
    foreach ($destResults as $d) {
        if ($d['ok']) { $firstOk = $d; break; }
    }
    $log->record([
        'channel'    => $r['name'],
        'version'    => (string) ($r['entry']['version'] ?? ''),
        'release_date' => (string) ($r['entry']['release_date'] ?? ''),
        'ok'         => $allOk,
        'http'       => $firstOk ? (int) $firstOk['http'] : (int) ($destResults[0]['http'] ?? 0),
        'attempts'   => max(1, (int) array_sum(array_column($destResults, 'attempts'))),
        'error'      => $allOk ? '' : 'one or more destinations failed',
        'message_id' => $firstOk ? (string) $firstOk['message_id'] : '',
        'forced'     => $force,
        'pinged'     => [
            'roles' => (array) ($payload['allowed_mentions']['roles'] ?? []),
            'users' => (array) ($payload['allowed_mentions']['users'] ?? []),
        ],
        'destinations' => $destResults,
    ]);

    return $allOk ? 0 : 3;
}

/**
 * Build the per-send ping overrides from CLI flags.
 *
 *   --ping-roles=111,222 --ping-users=333  --no-pings
 *
 * Returns the shape expected by Changelog_Futuristic_Layout::build():
 *   [ 'ping_role_ids' => [...], 'ping_user_ids' => [...], 'no_pings' => bool ]
 *
 * @param array<string,mixed> $opts
 * @return array<string,mixed>
 */
function changelog_announcement_ping_overrides(array $opts): array
{
    $overrides = [];
    if (!empty($opts['no-pings'])) {
        $overrides['no_pings'] = true;
        return $overrides;
    }
    $roles = changelog_announcement_split_csv((string) ($opts['ping-roles'] ?? ''));
    if ($roles) $overrides['ping_role_ids'] = $roles;
    $users = changelog_announcement_split_csv((string) ($opts['ping-users'] ?? ''));
    if ($users) $overrides['ping_user_ids'] = $users;
    return $overrides;
}

/** @return array<int,string> */
function changelog_announcement_split_csv(string $s): array
{
    $s = trim($s);
    if ($s === '') return [];
    $out = [];
    foreach (preg_split('/\s*,\s*/', $s) as $piece) {
        $piece = trim((string) $piece);
        if ($piece !== '') $out[] = $piece;
    }
    return $out;
}

/**
 * Tiny CLI parser. Returns [command, opts].
 *
 * Supports:  cmd  --key=value  --key value  --flag
 */
function changelog_announcement_parse_argv(array $argv): array
{
    $cmd = isset($argv[1]) ? trim((string) $argv[1]) : '';
    $opts = [];
    $n = count($argv);
    for ($i = 2; $i < $n; $i++) {
        $tok = (string) $argv[$i];
        if (strncmp($tok, '--', 2) !== 0) continue;
        $body = substr($tok, 2);
        if (strpos($body, '=') !== false) {
            [$k, $v] = explode('=', $body, 2);
            $opts[$k] = $v;
            continue;
        }
        $next = $argv[$i + 1] ?? null;
        if ($next !== null && strncmp((string) $next, '--', 2) !== 0) {
            $opts[$body] = (string) $next;
            $i++;
        } else {
            $opts[$body] = true;
        }
    }
    return [$cmd, $opts];
}
