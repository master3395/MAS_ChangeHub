# Discord Components V2 integration

MAS ChangeHub uses Discord **Components V2** for both Internet Archive and Contabo notifications.

## Configuration

### Internet Archive (`snapshot_config.conf`)

```bash
DISCORD_WEBHOOK_ENABLED=true
DISCORD_WEBHOOK_URL="https://discord.com/api/webhooks/..."
DISCORD_USE_CV2=true
DISCORD_HERO_IMAGE_URL="https://newstargeted.com/assets/status-cv2/archive.png?v=2"
```

### Contabo (`contabo/config.php`)

```php
define('DISCORD_WEBHOOK_ENABLED', true);
define('DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/...');
define('DISCORD_USE_CV2', true);
define('DISCORD_HERO_IMAGE_URL', 'https://newstargeted.com/assets/status-cv2/contabo.png');
```

## Shared library

| File | Role |
|------|------|
| `lib/discord_cv2_builder.php` | Container, Section, Thumbnail, TextDisplay, webhook POST |
| `lib/discord_notify_archive.php` | Wayback snapshot report layout |
| `lib/discord_notify_contabo.php` | Contabo snapshot report layout |
| `lib/discord_cv2_send.php` | CLI stdin JSON to archive sender |

## Tests

```bash
./test-discord-webhook.sh
php contabo/test-discord-webhook.php
```

## Webhook requirements

- Payload must set `flags: 32768`
- Webhook URL must include `?with_components=true` (handled automatically in PHP helpers)
- Use PNG hero URLs only (not `.ico`)
