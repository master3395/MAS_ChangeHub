# Contributing to MAS ChangeHub

Thank you for helping improve MAS ChangeHub.

Read [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) before participating.

## What this project contains

- **Bash** scripts for Internet Archive snapshots (`website_snapshot.sh`, `menu.sh`, helpers in `lib/`)
- **PHP** for Contabo VPS snapshot management (`contabo/`)
- **Shared PHP** Discord Components V2 builders (`lib/discord_cv2_builder.php`)

## Before you open a pull request

1. Do not include secrets. Never commit `snapshot_config.conf`, `contabo/config.php`, webhook URLs, or API keys.
2. Test syntax:
   - `bash -n website_snapshot.sh menu.sh`
   - `find contabo lib -name '*.php' -exec php -l {} \;`
3. If you change Discord payloads, test with:
   - `./test-discord-webhook.sh` (archive)
   - `php contabo/test-discord-webhook.php` (Contabo)
4. Keep PHP modules under 500 lines; split into `lib/` when needed.
5. Put new `.md` / `.txt` docs in `guides/`, not the repository root (except community profile files).

## Pull request flow

1. Fork [master3395/MAS_ChangeHub](https://github.com/master3395/MAS_ChangeHub)
2. Create a feature branch from `main`
3. Make focused changes with a clear commit message
4. Open a PR describing what changed and how you tested it

## Coding conventions

- Bash: use `SCRIPT_DIR` for paths; avoid hardcoded `/home/...` except in example configs
- PHP: guard config with `CONTABO_SNAPSHOT_INIT`; use prepared patterns from existing files
- Discord CV2: use `flags: 32768` and `with_components=true` on webhook URLs
- Dates in user-facing Discord text: `dd/mm/yyyy kl. HH:MM` (Europe/Oslo)

## Questions

Open a GitHub Issue for bugs or feature requests. For security issues, see [SECURITY.md](SECURITY.md).
