# MAS ChangeHub

Unified snapshot tooling for **NewsTargeted**: Internet Archive (Wayback Machine) website captures and **Contabo VPS** instance snapshots.

## Overview

| Product | Path | Schedule (server) |
|---------|------|-------------------|
| Internet Archive snapshots | `website_snapshot.sh` | Daily (configurable via `snapshot_config.conf`) |
| Contabo VPS snapshots | `contabo/snapshot-manager.php` | Daily via cron (configurable in `contabo/config.php`) |

Both products send **Discord Components V2** status reports when webhooks are enabled.

## Quick start

### Internet Archive

1. Copy `snapshot_config.conf.example` to `snapshot_config.conf` and set IA keys + Discord webhook.
2. Run `./menu.sh` or `./website_snapshot.sh`.

### Contabo

1. Copy `contabo/config.php.example` to `contabo/config.php` and set Contabo API credentials + Discord webhook.
2. Run `php contabo/snapshot-manager.php` or use menu option **11**.

### Interactive menu

```bash
./menu.sh
```

## Layout

| Path | Purpose |
|------|---------|
| `website_snapshot.sh` | Internet Archive snapshot runner |
| `snapshot_config.conf` | IA secrets (gitignored) |
| `contabo/snapshot-manager.php` | Contabo API snapshot manager |
| `contabo/config.php` | Contabo secrets (gitignored) |
| `lib/discord_cv2_builder.php` | Shared Discord Components V2 helpers |
| `lib/discord_notify_archive.php` | IA Discord CV2 payloads |
| `lib/discord_notify_contabo.php` | Contabo Discord CV2 payloads |
| `menu.sh` | Interactive CLI for both products |
| `to-do/` | Guides and operator documentation |

## Discord (Components V2)

- Set `DISCORD_USE_CV2=true` in `snapshot_config.conf` or `contabo/config.php`.
- Hero images: `https://newstargeted.com/assets/status-cv2/archive.png` and `contabo.png`.
- Test: `./test-discord-webhook.sh` (archive) or `php contabo/test-discord-webhook.php` (Contabo).

## Security

Never commit `snapshot_config.conf` or `contabo/config.php`. They contain API keys and webhook URLs.

See [SECURITY.md](SECURITY.md) for vulnerability reporting.

## Community

- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Contributing](CONTRIBUTING.md)
- [License](LICENSE) (MIT)

## Documentation

Full guides live in `to-do/`, including Contabo API setup and schedule configuration.
