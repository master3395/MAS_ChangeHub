# Changelog Announcement

CLI tool that posts changelog updates from any NewsTargeted project to
Discord using **Components V2** with a futuristic 3-row layout (no rich
embeds). One unified config, per-channel webhooks + sources, no duplicate
secrets.

- Source-of-truth for secrets: `config.php` (deny over HTTP via `.htaccess`).
- Each channel reads from its own changelog store (MariaDB, JSON, or HTTPS).
- Each channel posts to its own Discord webhook with its own optional
  `@notify-role` ping.
- Append-only log at `logs/announcements.jsonl` prevents accidental
  double-posts (override with `--force`).

## Folder layout

```
changelog-announcement/
  cli.php                       Main CLI entry
  config.php                    Config + channel registry (secrets here)
  bin/
    announce-changelog          Bash wrapper (sources optional env file)
  modules/
    sources/
      source_interface.php
      mariadb_source.php        Reads webhook.newstargeted.com style table
      json_source.php           Reads dashboard-style entries[] JSON file
      http_source.php           Reads a remote JSON feed
    renderer/
      components_v2.php         Tiny builders for type 17/10/14/9/11/1/2
      futuristic_layout.php     3-row layout: header / body / footer
    webhook/
      discord_webhook.php       POST + retry + 429 backoff
      announcement_log.php      JSONL log (dedupe + audit)
    channels/
      channel_registry.php      Resolves a channel name -> source
    util/
      html_to_markdown.php      <h5>/<ul>/<li>/<code> -> Discord markdown
  to-do/
    README.md                   This file
    CHANGELOG.md                Tool changelog (not the announcements log)
  sql/
    announcement_log.sql        Optional MariaDB mirror of the JSONL log
  logs/                         JSONL announcement log (created on first run)
  Test/                         Smoke-test scripts
```

## Quick start

```bash
cd /home/MAS_ChangeHub/changelog-announcement

# 1. See which channels are configured.
./bin/announce-changelog list

# 2. Dry-run the layout for one channel (no HTTP call).
./bin/announce-changelog preview --channel=webhook-proxy

# 3. See the raw Components V2 JSON we would POST.
./bin/announce-changelog show --channel=webhook-proxy --version=4.0.10

# 4. Actually announce.
./bin/announce-changelog send --channel=webhook-proxy --version=4.0.10
```

## Channels (defaults)

| Channel        | Source                                                            | Notes                                       |
|----------------|-------------------------------------------------------------------|---------------------------------------------|
| `webhook-proxy`| MariaDB `changelog_entries` (via webhook.newstargeted.com config) | HTML body, parsed into h5 sub-sections      |
| `dashboard`    | `dashboard_EXT/data/changelog.json` (entries[])                   | Added/Changed/Fixed categories              |
| `api`          | `https://api.newstargeted.com/changelog.json`                     | HTML body                                   |
| `bot`          | Same JSON file as dashboard, posted with the `@Notify` role ping  | Pinged role: `617517540125442087`           |
| `diabetes`       | `diabetes.newstargeted.com/to-do/changelog.json`                  | DiabetesTech Hub; footer + sidebar docs in v1.3.7+ |

Add new channels by appending to `'channels' => [...]` in `config.php`.
Nothing else needs touching: the renderer is generic.

## Adding a webhook URL

Webhooks can be set in one of three places (highest priority first):

1. **Per-channel env var**, set in `bin/announce-changelog.env`
   (chmod 600, NOT committed). Variable names per channel:
   - `CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_URL`
   - `CHANGELOG_ANNOUNCEMENT_DASHBOARD_URL`
   - `CHANGELOG_ANNOUNCEMENT_API_URL`
   - `CHANGELOG_ANNOUNCEMENT_BOT_URL`
2. **`webhook_url`** literal in `config.php` (only for one-off testing).
3. For the webhook-proxy channel, fall back to
   `getWebhookConfig('CHANGELOG_ANNOUNCE_WEBHOOK_URL')` in
   `/home/newstargeted.com/webhook.newstargeted.com/config.php`.

Notify role/user IDs follow the same pattern, with these env-name suffixes:

| Suffix      | Type    | Format                  | Where it lands                                  |
|-------------|---------|-------------------------|--------------------------------------------------|
| `_ROLE`     | single  | one snowflake           | back-compat; folded into the role list           |
| `_ROLES`    | list    | `id1,id2,id3`           | pings all listed roles                           |
| `_USERS`    | list    | `id1,id2,id3`           | pings all listed users                           |

Per-channel examples (set in `bin/announce-changelog.env`):

```bash
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_ROLES='1503...,1504...'
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_USERS='80357836940382208'

CHANGELOG_ANNOUNCEMENT_BOT_ROLES='617517540125442087,1505...'
```

You can also override pings per-send from the CLI without editing config:

```bash
# Add pings for this send only:
./bin/announce-changelog send --channel=webhook-proxy --version=4.0.10 \
    --ping-roles=1503...,1504... --ping-users=80357836940382208

# Silent post (suppress every configured ping):
./bin/announce-changelog send --channel=webhook-proxy --version=4.0.10 --no-pings
```

The renderer shows the pings as one compact line in the footer row, e.g.
`🔔 <@&1503...> <@&1504...>  •  👤 <@80357836940382208>`. The
`allowed_mentions` whitelist is built from exactly those IDs, so
`@everyone`, `@here`, and any mention that someone slips into the body
cannot ping anyone unintended.

## Components V2 layout

```
Container (accent = neon cyan #00E5FF)
  TextDisplay   -# `▰▰▰  ▱▱▱  CHANGELOG  ▱▱▱  ▰▰▰`
  TextDisplay   # 🛰 webhook.newstargeted.com  »  `v4.0.10`
                > -# `Patch`  •  Released 25/05/2026
  TextDisplay   > Lift CPU quota on the webhook slice
  Separator
  TextDisplay   ### ✨ »  What changed (operations)
                >  ›  Raised CPUQuota on webhook-cpu-limit.slice ...
                >  ›  Applied live with systemctl set-property ...
                >  ›  Restarted webhook-cpu-attach.timer ...
  TextDisplay   ### ✨ »  Why it mattered
                >  ›  enhanced-webhook-proxy was wedging: ...
                >  ›  cgroup stats showed about 58% of CFS ...
  Separator
  TextDisplay   🔔 <@&617517540125442087>          (only when notify role set)
  TextDisplay   > -# ⚡ NewsTargeted Webhook Proxy • Announced 25/05/2026 kl. 04:55
  ActionRow     [🔗 View Full Changelog]  [🌐 Visit Site]  [Jump to v4.0.10]
```

## Security

- `config.php` is the only file that holds webhook URLs / DB passwords.
- `.htaccess` denies all HTTP access to this directory.
- The CLI refuses to run via PHP-FPM (`PHP_SAPI !== 'cli'`).
- Only the configured notify role can be mentioned (allowed_mentions filter).
- Payloads are size-checked before send (64 KB ceiling, well under Discord's).
- Retries use exponential delay and honor `Retry-After` on HTTP 429.

## Operator notes

- Re-run `send` for the same `{channel,version}` is a no-op (returns exit
  code 4) unless `--force` is passed.
- The JSONL log is local and append-only. Rotate manually if it grows past
  ~50 MB; lines are independent.
- A SQL mirror schema lives in `sql/announcement_log.sql`. The tool itself
  does not need it; it is for dashboards that want to query history.
- All dates rendered for users follow Norwegian Bokmal: `dd/mm/yyyy kl. HH:MM`.

## Application emojis (Icons8 white PNGs)

The renderer can use real bot-owned **Discord application emojis** for the
title glyph, body section headers, footer glyph, and button emojis. Until
you upload them they automatically fall back to Unicode glyphs so the
layout still works.

### Files

```
assets/icons8/nt_workflow.png        (Workflow,       Icons8 jO04AMoNyDYN)
assets/icons8/nt_settings.png        (Settings,       Icons8 Zydyx4gBcOrY)
assets/icons8/nt_verified.png        (Done,           Icons8 TUfmJDlYw1B4)
assets/icons8/nt_download.png        (Download,       Icons8 aO3W9kKC9PMv)
assets/icons8/nt_external_link.png   (External Link,  Icons8 o90MnZhnB2CM)
assets/emoji-registry.json           (logical name -> file + fallback + app id)
```

### Upload them once with `sync-emojis`

Application emojis live on a Discord **application** (a.k.a. a bot/app
client id). Add the application id and a bot token to
`bin/announce-changelog.env`:

```bash
CHANGELOG_ANNOUNCEMENT_DISCORD_APP_ID='123456789012345678'
CHANGELOG_ANNOUNCEMENT_DISCORD_BOT_TOKEN='your bot token'
```

Then:

```bash
./bin/announce-changelog emoji-list        # what's registered, what's mapped
./bin/announce-changelog sync-emojis       # GET existing + POST missing
```

`sync-emojis` is idempotent: if an emoji named `nt_workflow` already
exists on your application, the script reuses its id instead of creating
a duplicate. Resolved ids are written back into
`assets/emoji-registry.json` so subsequent renders include
`<:nt_workflow:APPLICATION_EMOJI_ID>` directly in the message.

### How the renderer uses them

| Logical name        | Where it shows up                                         |
|---------------------|-----------------------------------------------------------|
| `nt_workflow`       | Title (`# 🛰️ project » v…`) and footer metadata line     |
| `nt_settings`       | Body section header (default; "Changes", "Why it mattered", "Operator notes", …) |
| `nt_verified`       | Body section header when heading matches `/verif|test|pass|done|added|fix/i` |
| `nt_download`       | "View Full Changelog" button                              |
| `nt_external_link`  | "Visit Site" and "Jump to vX" buttons                     |

If a logical name has no `application_emoji_id` yet, the renderer falls
back to the `fallback` Unicode glyph defined in the registry (so
production never blocks waiting for the sync to run).

### Why this requires a bot, not the webhook itself

Plain incoming webhooks cannot manage emojis. The application emojis
get created on a Discord **application** (the bot's app id), and any
message in the workspace (webhook or bot) can reference them by id with
`<:name:id>` once they exist. The bot token is only used by
`sync-emojis`; sending announcements still uses the channel webhook URL.

## Branding (per channel, all optional)

Every channel can override the look of its own announcement. All keys can
be set as env vars (with the prefix matching the channel) so the secrets
file stays the single source of truth:

| Channel field      | Env suffix    | Renders as                                  |
|--------------------|---------------|---------------------------------------------|
| `webhook_username` | `*_NAME`      | The webhook display name                    |
| `avatar_url`       | `*_AVATAR`    | The webhook avatar (per message)            |
| `logo_url`         | `*_LOGO`      | Top hero image (Components V2 MediaGallery) |
| `thumbnail_url`    | `*_THUMB`     | Right-side title accessory (Section+Thumb)  |
| `footer_message`   | `*_FOOTER`    | Extra subtle line in the footer row         |

Example for `bin/announce-changelog.env`:

```bash
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_NAME='NewsTargeted Webhook Proxy'
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_AVATAR='https://newstargeted.com/assets/logo.png'
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_LOGO='https://newstargeted.com/assets/banner.png'
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_THUMB='https://newstargeted.com/assets/icon.png'
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_FOOTER='Production change. Operator action required: none.'
```

## Multi-webhook fan-out (one channel, many destinations)

Each channel can post to any number of Discord webhooks. Pick whichever
form you prefer:

```bash
# Single URL (current behaviour):
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_URL='https://discord.com/api/webhooks/AAA/aaa'

# Comma-separated (in the same single var):
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_URL='https://discord.com/api/webhooks/AAA/aaa,https://discord.com/api/webhooks/BBB/bbb'

# Or as an explicit list (env var with the `_URLS` suffix, also CSV):
CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_URLS='https://discord.com/api/webhooks/AAA/aaa,https://discord.com/api/webhooks/BBB/bbb'
```

The sender POSTs the same Components V2 payload to every resolved URL,
records each destination separately in `logs/announcements.jsonl`
(`destinations[]`), and only marks the announcement `ok=true` if *every*
destination succeeded.

Per-send override (skip the configured list entirely):

```bash
./bin/announce-changelog send --channel=webhook-proxy --version=4.0.10 \
    --to='https://discord.com/api/webhooks/AAA/aaa,https://discord.com/api/webhooks/BBB/bbb'
```

The dedupe key is still `{channel, version}`. A single logical change is
recorded once per channel, even when fanned out to several Discord rooms.

## Adding a new channel (5 lines)

```php
'extensions' => [
    'label'        => 'NewsTargeted Extensions',
    'project_tag'  => 'extensions.newstargeted.com',
    'public_url'   => 'https://extensions.newstargeted.com/changelog',
    'site_url'     => 'https://extensions.newstargeted.com/',
    'webhook_url'  => changelog_announcement_env('CHANGELOG_ANNOUNCEMENT_EXTENSIONS_URL', ''),
    'notify_role_id' => changelog_announcement_env('CHANGELOG_ANNOUNCEMENT_EXTENSIONS_ROLE', ''),
    'source' => [ 'type' => 'json', 'path' => '/.../changelog.json',
                  'list_key' => 'entries', 'category_key' => 'categories' ],
],
```
