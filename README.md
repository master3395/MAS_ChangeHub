# MAS_ChangeHub

Automated Wayback Machine snapshots for NewsTargeted sites and subdomains.

## Overview

This repository contains shell tooling that submits configured URLs to the Internet Archive Save Page Now API, logs results, and can notify Discord on completion.

## Quick start

1. Copy `snapshot_config.conf.example` to `snapshot_config.conf` and fill in your Internet Archive and Discord values.
2. Run `./menu.sh` for an interactive menu, or `./website_snapshot.sh` for a scheduled run.
3. See `to-do/README.md` for full documentation.

## Layout

| Path | Purpose |
|------|---------|
| `website_snapshot.sh` | Main snapshot runner |
| `snapshot_config.conf` | Local secrets (gitignored) |
| `snapshot_config.conf.example` | Template without secrets |
| `menu.sh` | Interactive CLI |
| `schedule_manager.sh` | Cron schedule helper |
| `to-do/` | Guides and changelogs |

## Security

Never commit `snapshot_config.conf`. It holds API keys and webhook URLs.

## License

MIT (see `LICENSE`).
