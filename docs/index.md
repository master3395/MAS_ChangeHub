---
layout: default
title: Home
---

# MAS ChangeHub

Unified snapshot tooling for **NewsTargeted**: Internet Archive (Wayback Machine) captures and **Contabo VPS** backups, with Discord Components V2 notifications.

<p>
  <a class="btn" href="https://github.com/master3395/MAS_ChangeHub">View on GitHub</a>
  <a class="btn" href="https://github.com/master3395/MAS_ChangeHub/releases">Releases</a>
  <a class="btn" href="https://github.com/sponsors/master3395">Sponsor</a>
</p>

## What it includes

| Product | Entry | Docs |
|---------|-------|------|
| Internet Archive snapshots | `website_snapshot.sh` / `./menu.sh` | [Archive guide](../guides/README.md) |
| Contabo VPS snapshots | `contabo/snapshot-manager.php` | [Contabo guide](../guides/CONTABO-README.md) |
| Discord CV2 alerts | `lib/discord_cv2_*` | [CV2 guide](../guides/DISCORD-CV2-INTEGRATION.md) |

## Quick start

```bash
git clone https://github.com/master3395/MAS_ChangeHub.git
cd MAS_ChangeHub
cp config/snapshot_config.conf.example config/snapshot_config.conf
cp contabo/config.php.example contabo/config.php
chmod 600 config/snapshot_config.conf contabo/config.php
./menu.sh
```

## Documentation index

- [Configuration file guide](../guides/CONFIG-FILE-GUIDE.md)
- [Schedule and cron](../guides/SCHEDULE-FREQUENCY-GUIDE.md)
- [Contabo API setup](../guides/CONTABO-API-SETUP-GUIDE.md)
- [Quick start schedule](../guides/QUICK-START-SCHEDULE.txt)
- [Full README on GitHub](../README.md)

## Community

- [Contributing](../CONTRIBUTING.md)
- [Security policy](../SECURITY.md)
- [Code of Conduct](../CODE_OF_CONDUCT.md)
- [Report a bug](https://github.com/master3395/MAS_ChangeHub/issues/new/choose)

## License

MIT. See [LICENSE](../LICENSE).
